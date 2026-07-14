<div class="hidden">
    <div class="filter-box right-side-filter-container" style="{!! $style !!}" id="{{ $filterID }}">
        <form action="{!! $action !!}" class="form-horizontal" method="post">
            @csrf
            <div class="mb-1" style="height: 55px">
                <div class="p-1 position-fixed d-flex justify-content-between header">
                    <div>
                        <button type="submit" class="btn btn-sm btn-primary submit">
                            <i class="feather icon-search"></i> &nbsp;{{ __('admin.search') }}
                        </button>&nbsp;
                        <a href="{{ admin_url('game-license-search-clear') }}" class="reset btn btn-sm btn-white">
                            <i class="feather icon-rotate-ccw"></i> &nbsp;{{ __('admin.reset') }}
                        </a>
                    </div>
                </div>
            </div>

            @foreach($layout->columns() as $column)
                @foreach($column->filters() as $filter)
                    {!! $filter->render() !!}
                @endforeach
            @endforeach
        </form>
    </div>
</div>
