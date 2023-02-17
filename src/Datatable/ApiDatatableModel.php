<?php
namespace Joespark\Datatable;

class ApiDatatableModel extends ApiDatatable
{
    public $url;

    public $macro;

    public function __construct($url, $macro = null)
    {
        $this->url = $url;
        $this->macro = $macro;
    }
}