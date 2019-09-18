@php
    $title=isset($title)?$title:'Item';
    $titleObj=['singular'=>strtolower(str_singular($title)),'plural'=>strtolower(str_replace(' ','-',$title))];
    $base_url = (isset($bag['base_url']) && $bag['base_url']) ? $bag['base_url'] : '';

@endphp
<script>

    let title = @json($titleObj);
    const base_url = '{{$base_url?$base_url:url('')}}';
    let canEdit = false, canDelete = false;
    {{--    @can('edit '.strtolower($titleObj['singular']))--}}
        canEdit = true;
    {{--    @endcan--}}
            {{--            @can('delete '.strtolower($titleObj['singular']))--}}
        canDelete = true;
            {{--            @endcan--}}

    let canEditOrDelete = canEdit || canDelete;

    let table_selector = '#data-table';
    @if(isset($bag['table_selector']) && $bag['table_selector'])
        table_selector = '{{$bag['table_selector']}}';
    @endif

    // Extra Variables
    let extraVariables = [];
    @if(isset($bag['extraVariables']) && $bag['extraVariables'])
        extraVariables = @json($bag['extraVariables']);
    @endif

    // DataTable Extra Props
    let datatableExtraProps = [];
    @if(isset($bag['datatableExtraProps']) && $bag['datatableExtraProps'])
        datatableExtraProps = @json($bag['datatableExtraProps']);
    @endif

    if (typeof swalWithBootstrapButtons === 'undefined')
        swalWithBootstrapButtons = swal.mixin({
            confirmButtonClass: 'btn btn-success',
            cancelButtonClass: 'btn btn-danger',
            buttonsStyling: false,
        });

    function matchRecursion(string, obj) {
        matches = string.match(/\${(.*?)\}/);
        if (matches) {
            let value = obj[matches[1]];
            value = value ? value : '';
            string = string.replace(matches[0], `"${value}"`);
            matches = string.match(/\${(.*?)\}/);
            if (matches)
                return matchRecursion(string, obj);
        }
        return string;
    }

</script>
@if(isset($bag['actions']) && $bag['actions'])
    <script>
        let actions = true;

        $(document).on('click', '.deleteRow', function () {
            let id = $(this).data('id');
            let delete_url = $(this).data('url');
            delete_url = delete_url ? delete_url : `{{url($base_url)}}/${title.plural.toLowerCase()}/${id}`;
            swalWithBootstrapButtons.fire({
                title: 'Are you sure?',
                text: "You want to delete this " + title.singular.toLowerCase(),
                type: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'No, cancel!',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $.ajax({
                        type: 'POST',
                        url: delete_url,
                        data: {
                            _method: 'DELETE',
                            _token: '{{csrf_token()}}'
                        },
                        success: function (response) {
                            if (response.success)
                                table.ajax.reload();
                            swalWithBootstrapButtons.fire(response.title, response.message, response.type);
                        }
                    });
                }
            });
        });
    </script>
@else
    <script>
        let actions = false;
    </script>
@endif

<script>
    function contains(haystack, needle) {
        return haystack.includes(needle) ? haystack : needle;
    }

    $(function () {
        let columns = [];
        let columnsObj = @json(@$bag['columns']);
        let autoRenderColumns = @json(@$bag['autoRenders']);
        let extraActions = @json(@$bag['extraActions']);

        for (let key in columnsObj) {


            let value = columnsObj[key];
            let obj = {data: value, name: value};

            // if (isNaN(key)) {
            //     obj = {data: key, name: value};
            // }


            if (isNaN(key)) {
                // TODO below part needs to be improved. (by implementing the fname+lmane functionality)
                if (typeof value === "object") {
                    // For inputs in column
                    obj = {
                        "render": function (data, type, row, meta) {
                            if (row) {
                                let output = '';

                                if (!value.type)
                                    return '';

                                let columnValue = filterForEval('row', row, key);
                                classes = value.classes ? value.classes : '';

                                let extraAttributes = [];
                                if (value.extraAttributes) {
                                    for (let key in value.extraAttributes) {
                                        let attribute = value.extraAttributes[key];
                                        let matches = attribute.match(/\{(.*?)\}/);
                                        if (matches) {
                                            let matchedWord = matches[1];
                                            firstHalf = attribute.split('{')[0];
                                            secondHalf = attribute.split('}')[1];
                                            attribute = firstHalf + eval(`row.${matchedWord}`) + secondHalf;
                                        }
                                        extraAttributes.push(attribute);
                                    }
                                }
                                extraAttributes = extraAttributes.join(' ');

                                switch (value.type) {
                                    case 'custom':
                                        let code = matchRecursion(value.js_code, row);
                                        output = eval(code);
                                        break;
                                    case 'dropdown':
                                        let options = '';

                                        value.data.forEach((option, i) => {
                                            options += `<option value="${i}" ${columnValue == i ? 'selected' : ''}>${option}</option>`;
                                        });
                                        output = `<select class="form-control ${classes}" ${extraAttributes}>${options}</select>`;
                                        break;
                                    // TODO, add other types. e.g. text,checkbox,radio,textarea
                                }

                                return output;
                            }
                        }
                    };
                } else if (value.includes(':=')) {
                    // For extra attribute, e.g, searchable:= false
                    let splitted = value.split(':=');
                    obj = {data: key, name: key};
                    obj[splitted[0]] = splitted[1];
                } else {
                    // For custom condition, e.g. status?1:'Active':'Inactive'
                    obj = {
                        "render": function (data, type, row, meta) {
                            if (row) {
                                let output = '';
                                let matches = value.match(/\@{{(.*?)\}}/);
                                if (matches) {
                                    let valueFirstHalf = value.split('@{{')[0];
                                    let valueSecondHalf = value.split('}}')[1];
                                    let matchedWord = matches[1];

                                    let matchesVariable = matchedWord.match(/\{(.*?)\}/);
                                    if (matchesVariable) {
                                        let matchesVariableWord = matchesVariable[1];
                                        let variable = eval(`row.${matchesVariableWord}`);
                                        firstHalf = matchedWord.split('{')[0];
                                        secondHalf = matchedWord.split('}')[1];
                                        output = eval(firstHalf + variable + secondHalf);
                                    }
                                    output = valueFirstHalf + output + valueSecondHalf;
                                } else {
                                    output = eval(`row.${value}`)
                                }
                                return output;
                            }
                        }
                    }
                }
            }
            // END

            if (autoRenderColumns) {
                for (let columnName in autoRenderColumns) {
                    if (columnName == value) {
                        let renderType = autoRenderColumns[columnName];
                        obj = {
                            "render": function (data, type, row, meta) {
                                if (row) {
                                    let columnValue = filterForEval('row', row, columnName);
                                    switch (renderType) {
                                        case 'date':
                                            // returning formatted date
                                            return formattedDate(columnValue);
                                        case 'time':
                                            // returning formatted time
                                            return formattedTime(columnValue);
                                        case 'datetime':
                                            // returning formatted datetime
                                            return formattedDate(columnValue, true);
                                        case contains(renderType, 'limit_'):
                                            let limit = parseInt(renderType.replace('limit_', '')) || 0;
                                            return (columnValue && columnValue.length > limit) ? columnValue.substr(0, limit) + ' ....' : columnValue;
                                        case 'money':
                                            // returning numbers in money format
                                            return isNaN(columnValue) ? columnValue : parseInt(columnValue).toLocaleString();
                                    }
                                }
                            }
                        };
                    }
                }
            }
            columns.push(obj);
        }

        if ((actions && canEditOrDelete) || extraActions) {

            if ($(table_selector + ' th.actions').length < 1) {
                $(table_selector + ' thead tr').append('<th class="actions">Actions</th>');
                $(table_selector + ' tfoot tr').append('<th class="actions">Actions</th>');
            }

            let renderKey = 'id';

            // Getting action attributes
            actions = @json(@$bag['actions']);
            if (actions && typeof actions === 'object') {
                renderKey = typeof actions.renderKey !== 'undefined' ? actions.renderKey : renderKey;
            }

            let culomn = {
                "render": function (data, type, row, meta) {
                    let actionButtons = [];
                    if (extraActions) {
                        for (let key in extraActions) {
                            let action = extraActions[key];
                            let href = null;
                            if (typeof action.href !== 'undefined') {
                                href = action.href;
                                let matches = href.match(/\{(.*?)\}/);
                                if (matches) {
                                    let matchedWord = matches[1];
                                    firstHalf = href.split('{')[0];
                                    secondHalf = href.split('}')[1];
                                    href = firstHalf + eval(`row.${matchedWord}`) + secondHalf;
                                }
                            }

                            href = href ? `{{url($base_url)}}/${href}` : 'javascript:void(0)';
                            let extraAttributes = [];
                            if (typeof action.extraAttributes !== 'undefined') {
                                for (let key in action.extraAttributes) {
                                    let attribute = action.extraAttributes[key];
                                    let matches = attribute.match(/\{(.*?)\}/);
                                    if (matches) {
                                        let matchedWord = matches[1];
                                        firstHalf = attribute.split('{')[0];
                                        secondHalf = attribute.split('}')[1];
                                        attribute = firstHalf + eval(`row.${matchedWord}`) + secondHalf;
                                    }
                                    extraAttributes.push(attribute);
                                }
                            }
                            extraAttributes = extraAttributes.join(' ');
                            let button = `<a class='text-primary ${action.extraClasses}' ${extraAttributes} data-id="${row.id}" title='${action.title}' href="${href}"><i class="${action.iconClasses}"></i></a>`;
                            actionButtons.push(button);
                        }
                    }

                    const renderValue = eval(`row.${renderKey}`);

                    // Checking if separate action exit, then it will ignore the default edit,delete functionality.
                    let separateActions = actions.separateActions;
                    if (separateActions) {
                        if (separateActions.edit) {
                            let code = matchRecursion(separateActions.edit, row);
                            output = eval(code);
                            actionButtons.push(output);
                        }

                        if (separateActions.delete) {
                            let code = matchRecursion(separateActions.delete, row);
                            output = eval(code);
                            actionButtons.push(output);
                        }
                    } else {
                        if (actions && canEdit) {
                            let edit = `<a class='text-primary' title='Edit' href="{{url($base_url)}}/${title.plural.toLowerCase()}/${renderValue}/edit"><i class="fa fa-edit"></i></a>`;
                            actionButtons.push(edit);
                        }

                        if (actions && canDelete) {
                            let del = `<a class='text-danger deleteRow' title='Delete' href="javascript:void(0)" data-id="${renderValue}"><i class="fa fa-trash"></i></a>`;
                            actionButtons.push(del);
                        }
                    }

                    return actionButtons.filter(function (el) {
                        return el;
                    }).join('&nbsp / &nbsp');
                }
            };
            columns.push(culomn)
        }

        let url = `{{url($base_url)}}/${title.plural.toLowerCase()}/get-basic-data`;
        @if(isset($bag['url']) && $bag['url'])
            url = '{{$bag['url']}}';
                @endif


        let datatableProps = {
                processing: true,
                serverSide: true,
                ajax: url,
                columns
            };

        datatableExtraProps.forEach(function (prop) {
            datatableProps[prop.key] = prop.value;
        });
        
        table = $(table_selector).DataTable(datatableProps);
    });
</script>
