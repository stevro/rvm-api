<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\City;
use App\County;

class StaticController extends Controller
{
    /**
     * @SWG\Get(
     *   tags={"Statics"},
     *   path="/api/cities",
     *   summary="Return all cities",
     *   operationId="getAllCities",
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function getAllCities(Request $request)
    {
        $params = $request->query();
        $cities = City::query();

        applyFilters($cities, $params, array(
            '1' => array( 'county_id', 'ilike' ),
            '2' => array( 'slug', 'ilike' ),
            //'3' => array( 'organisation.name', 'ilike')
        ));

        applySort($cities, $params, array(
            '1' => 'name',
            //'2' => 'type_name',
            //'3' => 'quantity',
            //'4' => 'organisation', //change to nr_org
        ));

        $pager = applyPaginate($cities, $params);

        return response()->json(array(
            "pager" => $pager,
            "data" => $cities->get()
        ), 200); 
    }

    /**
     * @SWG\Get(
     *   tags={"Statics"},
     *   path="/api/counties",
     *   summary="Return all counties",
     *   operationId="getAllCounties",
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function getAllCounties(Request $request)
    {
        $params = $request->query();
        $counties = County::query();

        applyFilters($counties, $params, array(
            '1' => array( 'slug', 'ilike' ),
            //'2' => array( 'county', 'ilike' ),
            //'3' => array( 'organisation.name', 'ilike')
        ));

        applySort($counties, $params, array(
            '1' => 'name',
            //'2' => 'type_name',
            //'3' => 'quantity',
            //'4' => 'organisation', //change to nr_org
        ));

        $pager = applyPaginate($counties, $params);

        return response()->json(array(
            "pager" => $pager,
            "data" => $counties->get()
        ), 200); 
    }

}
