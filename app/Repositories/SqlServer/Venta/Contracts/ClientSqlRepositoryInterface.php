<?php
namespace App\Repositories\SqlServer\Venta\Contracts;

interface ClientSqlRepositoryInterface
{
     public function getCheckoutClientSegment($ruc);
     public function getQueryListSegments();
     public function getQueryListSubSegmentsBySegment($segmento);

      public function checkQueryClientWithSegment($ruc);
}
