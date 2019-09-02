@php
    $title=isset($title)?$title:'Item';
    $titleObj=['singular'=>strtolower(str_singular($title)),'plural'=>strtolower(str_replace(' ','-',$title))];
    $base_url = (isset($bag['base_url']) && $bag['base_url']) ? $bag['base_url'] : '';

@endphp
<script>

    let title = @json($titleObj);
    let canEdit = false, canDelete = false;
    {{--    @can('edit '.strtolower($titleObj['singular']))--}}
        canEdit = true;
    {{--    @endcan--}}
            {{--            @can('delete '.strtolower($titleObj['singular']))--}}
        canDelete = true;
            {{--            @endcan--}}

    let canEditOrDelete = canEdit || canDelete;

</script>
@if(isset($bag['actions']) && $bag['actions'])
    <script>
        let actions = true;

        $(document).on('click', '.deleteRow', function () {
            let id = $(this).data('id');
            swalWithBootstrapButtons({
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
                        url: `{{url($base_url)}}/${title.plural.toLowerCase()}/${id}`,
                        data: {
                            _method: 'DELETE',
                            _token: '{{csrf_token()}}'
                        },
                        success: function (response) {
                            if (response.success)
                                table.ajax.reload();
                            swalWithBootstrapButtons(response.title, response.message, response.type);
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

        console.log('columnsObj',columnsObj)

        for (let key in columnsObj) {


            let value = columnsObj[key];
            let obj = {data: value, name: value};

            // if (isNaN(key)) {
            //     obj = {data: key, name: value};
            // }


            if (isNaN(key)) {
                // TODO below part needs to be improved. (by implementing the fname+lmane functionality)
                if(typeof value === "object"){
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
                } else if(value.includes(':=')){
                    // For extra attribute, e.g, searchable:= false
                    let splitted = value.split(':=');
                    obj = {data: key, name: key};
                    obj[splitted[0]] = splitted[1];
                } else{
                    // For custom condition, e.g. status?1:'Active':'Inactive'
                    let obj={
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
                                        // break;
                                        case 'time':
                                            // returning formatted time
                                            // return formattedTime(columnValue);
                                            break;
                                        case contains(renderType, 'limit_'):
                                            let limit = parseInt(renderType.replace('limit_', '')) || 0;

                                            return (columnValue && columnValue.length > limit) ? columnValue.substr(0, limit) + ' ....' : columnValue;
                                        // break;
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

            if ($('#data-table th.actions').length < 1) {
                $('#data-table thead tr').append('<th class="actions">Actions</th>');
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

                    if (actions && canEdit) {
                        let edit = `<a class='text-primary' title='Edit' href="{{url($base_url)}}/${title.plural.toLowerCase()}/${renderValue}/edit"><i class="fa fa-edit"></i></a>`;
                        actionButtons.push(edit);
                    }

                    if (actions && canDelete) {
                        let del = `<a class='text-danger deleteRow' title='Delete' href="javascript:void(0)" data-id="${renderValue}"><i class="fa fa-trash"></i></a>`;
                        actionButtons.push(del);
                    }

                    return actionButtons.join('&nbsp / &nbsp');
                }
            };
            columns.push(culomn)
        }

        let url = `{{url($base_url)}}/${title.plural.toLowerCase()}/get-basic-data`,
            table_selector = '#data-table';
        @if(isset($bag['url']) && $bag['url'])
            url = '{{$bag['url']}}';
        @endif

                @if(isset($bag['table_selector']) && $bag['table_selector'])
            table_selector = '{{$bag['table_selector']}}';
        @endif
            table = $(table_selector).DataTable({
            processing: true,
            serverSide: true,
            ajax: url,
            columns

        });
    });
</script>
