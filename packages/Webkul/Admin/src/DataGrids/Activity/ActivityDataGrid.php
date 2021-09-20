<?php

namespace Webkul\Admin\DataGrids\Activity;

use Illuminate\Support\Facades\DB;
use Webkul\UI\DataGrid\DataGrid;

class ActivityDataGrid extends DataGrid
{
    /**
     * Create datagrid instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->tabFilters = $this->prepareTabFilters("activities");
    }

    /**
     * Prepare query builder.
     *
     * @return void
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('activities')
            ->distinct()
            ->select(
                'activities.*',
                'leads.id as lead_id',
                'users.id as created_by_id',
                'users.name as created_by',
                'leads.title as lead_title',
            )
            ->leftJoin('activity_participants', 'activities.id', '=', 'activity_participants.activity_id')
            ->leftJoin('lead_activities', 'activities.id', '=', 'lead_activities.activity_id')
            ->leftJoin('leads', 'lead_activities.lead_id', '=', 'leads.id')
            ->leftJoin('users', 'activities.user_id', '=', 'users.id')
            ->whereIn('type', ['call', 'meeting', 'lunch']);


        $currentUser = auth()->guard('user')->user();

        if ($currentUser->view_permission != 'global') {
            if ($currentUser->view_permission == 'group') {
                $queryBuilder->where(function ($query) use($currentUser) {
                    $userIds = app('\Webkul\User\Repositories\UserRepository')->getCurrentUserGroupsUserIds();

                    $query->whereIn('activities.user_id', $userIds)
                        ->orWhereIn('activity_participants.user_id', $userIds);

                    return $query;
                });
            } else {
                $queryBuilder->where(function ($query) use ($currentUser) {
                    $query->where('activities.user_id', $currentUser->id)
                        ->orWhere('activity_participants.user_id', $currentUser->id);

                    return $query;
                });
            }
        }

        $this->addFilter('id', 'activities.id');
        $this->addFilter('created_by', 'users.name');
        $this->addFilter('user', 'activities.user_id');
        $this->addFilter('created_at', 'activities.created_at');

        $this->setQueryBuilder($queryBuilder);
    }

    /**
     * Add columns.
     *
     * @return void
     */
    public function addColumns()
    {
        $this->addColumn([
            'index'      => 'title',
            'label'      => trans('admin::app.datagrid.title'),
            'type'       => 'string',
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'              => 'user',
            'label'              => trans('admin::app.datagrid.created_by'),
            'type'               => 'hidden',
            'sortable'           => true,
            'filterable_type'    => 'dropdown',
            'filterable_options' => app('\Webkul\User\Repositories\UserRepository')->get(['id as value', 'name as label'])->toArray(),
        ]);

        $this->addColumn([
            'index'      => 'comment',
            'label'      => trans('admin::app.datagrid.comment'),
            'type'       => 'string',
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'   => 'lead',
            'label'   => trans('admin::app.datagrid.lead'),
            'type'    => 'string',
            'closure' => true,
            'wrapper' => function ($row) {
                $route = urldecode(route('admin.leads.index', ['view_type' => 'table', 'id[eq]' => $row->lead_id]));

                return "<a href='" . $route . "'>" . $row->lead_title . "</a>";
            },
        ]);

        $this->addColumn([
            'index'      => 'type',
            'head_style' => 'width: 70px',
            'label'      => trans('admin::app.datagrid.type'),
            'type'       => 'boolean',
        ]);

        $this->addColumn([
            'index'              => 'is_done',
            'head_style'         => 'width: 100px',
            'label'              => trans('admin::app.datagrid.is_done'),
            'type'               => 'boolean',
            'closure'            => true,
            'wrapper'            => function ($row) {
                if ($row->is_done) {
                    return '<span class="badge badge-round badge-success"></span>' . __("admin::app.common.yes");
                } else {
                    return '<span class="badge badge-round badge-danger"></span>' . __("admin::app.common.no");
                }
            },
            'filterable_type'    => 'dropdown',
            'filterable_options' => [
                [
                    'value' => 0,
                    'label' => __("admin::app.common.no"),
                ], [
                    'value' => 1,
                    'label' => __("admin::app.common.yes"),
                ]
            ],
        ]);

        $this->addColumn([
            'index'   => 'created_by',
            'label'   => trans('admin::app.datagrid.created_by'),
            'type'    => 'string',
            'closure' => true,
            'wrapper' => function ($row) {
                $route = urldecode(route('admin.settings.users.index', ['id[eq]' => $row->created_by_id]));

                return "<a href='" . $route . "'>" . $row->created_by . "</a>";
            },
        ]);

        $this->addColumn([
            'index'           => 'schedule_from',
            'head_style'      => 'width: 100px',
            'label'           => trans('admin::app.datagrid.schedule_from'),
            'title'           => true,
            'type'            => 'string',
            'sortable'        => true,
            'wrapper'         => function ($row) {
                return core()->formatDate($row->schedule_from);
            },
            'filterable_type' => 'date_range',
        ]);

        $this->addColumn([
            'index'           => 'schedule_to',
            'head_style'      => 'width: 100px',
            'label'           => trans('admin::app.datagrid.schedule_to'),
            'title'           => true,
            'type'            => 'string',
            'sortable'        => true,
            'wrapper'         => function ($row) {
                return core()->formatDate($row->schedule_to);
            },
            'filterable_type' => 'date_range',
        ]);

        $this->addColumn([
            'index'           => 'created_at',
            'head_style'      => 'width: 100px',
            'label'           => trans('admin::app.datagrid.created_at'),
            'title'           => true,
            'type'            => 'string',
            'sortable'        => true,
            'wrapper'         => function ($row) {
                return core()->formatDate($row->created_at);
            },
            'filterable_type' => 'date_range',
        ]);
    }

    /**
     * Prepare actions.
     *
     * @return void
     */
    public function prepareActions()
    {
        $this->addAction([
            'title'  => trans('ui::app.datagrid.edit'),
            'method' => 'GET',
            'route'  => 'admin.activities.edit',
            'icon'   => 'pencil-icon',
        ]);

        $this->addAction([
            'title'        => trans('ui::app.datagrid.delete'),
            'method'       => 'DELETE',
            'route'        => 'admin.activities.delete',
            'confirm_text' => trans('ui::app.datagrid.massaction.delete'),
            'icon'         => 'trash-icon',
        ]);
    }
}
