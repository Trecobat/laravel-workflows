<div id="settings-overlay" class="settings-overlay">
    <div class="container">
        <div class="row">
            <div class="col-md-12" style="margin-bottom: 20px;">
                <div class="settings-headline">
                    <h1>{!! $element::$icon !!} {{ $element::getTranslation()  }} {{__('workflows::workflows.Settings') }}</h1>
                </div>
                <div class="alert alert-warning">
                    <b>Il n'est possible d'utiliser la condition <i>'change'</i> que sur les models ayant un historique  <i>'HasHistories'</i>.  </b>
                </div>
                <div class="alert alert-info">
                    <b>Pour la condition <i>change</i>, les valeurs acceptées sont :</b>
                    <ul>
                        <li>Pour tester le changement : '', 'O', 'OUI', 'o', 'oui', '1'</li>
                        <li>Pour tester le non changement : '-', 'N', 'NON', 'n', 'non', '0'</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-12">
                <div class="settings-body">
                    <div id="builder"></div>
                    <script>

                            $('#builder').queryBuilder({

                                operators: ['equal', 'not_equal','change'],
                                allow_groups: false,
                                @if(!empty($element->conditions))
                                    rules: {!! $element->conditions !!},
                                @endif
                                filters: [
                                    @foreach($allFilters as $filterGroup => $filters)
                                        @foreach($filters as $name => $values)
                                            {
                                                id: '{{$filterGroup}}-{{$values}}',
                                                field: '{{$name}}',
                                                optgroup: '{{$filterGroup}}',
                                                type: 'string',
                                            },
                                        @endforeach
                                    @endforeach
                                ]
                            });

                    </script>
                </div>
            </div>
            <div class="col-md-12">
                <div class="settings-footer text-right">
                    <button class="btn btn-default"
                            onclick="closeConditions();">{{__('workflows::workflows.Close') }}</button>
                    <button class="btn btn-success"
                            onclick="saveConditions({{ $element->id }}, '{{ $element->family }}');">{{__('workflows::workflows.Save') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
