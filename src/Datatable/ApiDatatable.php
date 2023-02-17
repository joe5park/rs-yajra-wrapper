<?php

namespace Joespark\Datatable;

use Yajra\DataTables\DataTableAbstract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiDatatable extends DataTableAbstract
{
    public $params = [];

    public $limit = 10;

    public $offset = 0;

    public $url = '';

    public $macro = null;

    public static function canCreate($source)
    {
        return $source instanceof ApiDatatableModel;
    }

    public static function create($source)
    {
        return parent::create($source);
    }

    /**
     * @param ApiDatatable $model
     */
    public function __construct($model)
    {
        $this->url = $model->url;
        $this->macro = $model->macro;

        $this->request    = app('datatables.request');
        $this->config     = app('datatables.config');
    }

    protected function resolveCallbackParameter()
    {
        return $this;
    }

    protected function defaultOrdering()
    {
        $criteria = $this->request->orderableColumns();
        
        if (empty($criteria)) {
            return;
        }
        $this->params['order_by'] = $this->request->columnName($criteria[0]['column']);
        $this->params['order_dir'] = $criteria[0]['direction'];
    }
    
    public function globalSearch($keyword)
    {
        $this->params['search_query'] = $keyword;

        $this->isFilterApplied = true;
    }

    public function results()
    {
        if ($this->macro !== null) {
            $response = Http::{$this->macro}()->get($this->url, $this->params);
        } else {
            $response = Http::get($this->url, $this->params);
        }

        $responseArray = $response->json();
        
        $this->totalRecords = $responseArray['metadata']['total_data'] ?? 0;
        $this->filteredRecords = $responseArray['metadata']['total_filtered'] ?? 0;

        return collect($responseArray['data'] ?? []);
    }

    public function count()
    {
        return $this->totalRecords;
    }

    public function filteredCount()
    {
        return $this->filteredRecords;
    }

    public function totalCount()
    {
        return $this->totalRecords;
    }

    public function columnSearch()
    {
        $columns = $this->request->columns();

        foreach ($columns as $index => $column) {
            $column = $this->getColumnName($index);

            if (
                !$this->request->isColumnSearchable($index) ||
                $this->request->columnKeyword($index) === null ||
                $this->request->columnKeyword($index) === '' 
            ) {
                continue;
            }

            $keyword = $this->request->columnKeyword($index);

            $this->params['searches'][$column] = $keyword;

            $this->isFilterApplied = true;
        }
    }

    public function paging()
    {
        $limit = (int) $this->request->input('length') > 0 ? $this->request->input('length') : 10;
        $offset = $this->request->input('start');

        $this->params['limit'] = $limit;
        $this->params['offset'] = $offset;
    }

    public function make($mDataSupport = true)
    {
        try {
            $this->ordering();
            $this->filterRecords();
            $this->paginate();

            $results = $this->results();
            $processed = $this->processResults($results, $mDataSupport);
            $data = $this->transform($results, $processed);

            return $this->render($data);
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->errorResponse($th);
        }
    }

    protected function render(array $data)
    {
        $output = $this->attachAppends([
            'draw'            => (int) $this->request->input('draw'),
            'recordsTotal'    => $this->totalCount(),
            'recordsFiltered' => $this->filteredCount(),
            'data'            => $data,
        ]);

        if ($this->config->isDebugging()) {
            $output = $this->showDebugger($output);
        }

        foreach ($this->searchPanes as $column => $searchPane) {
            $output['searchPanes']['options'][$column] = $searchPane['options'];
        }

        return new JsonResponse(
            $output,
            200,
            $this->config->get('datatables.json.header', []),
            $this->config->get('datatables.json.options', 0)
        );
    }
}