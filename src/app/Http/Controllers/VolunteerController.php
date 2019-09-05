<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Volunteer;
use App\Course;
use App\City;
use App\County;
use App\Rules\Cnp;

class VolunteerController extends Controller
{
        /**
     * @SWG\Get(
     *   tags={"Volunteers"},
     *   path="/api/volunteers",
     *   summary="Return all volunteers",
     *   operationId="index",
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function index(Request $request)
    {
        $params = $request->query();
        $volunteers = Volunteer::query();

        applyFilters($volunteers, $params, array(
            //'1' => array( 'resource_type', 'ilike' ),
            '2' => array( 'county', 'ilike' ),
            '3' => array( 'organisation.name', 'ilike'),
           // '4' => array( 'specialization', 'ilike')
        ));

        applySort($volunteers, $params, array(
            '1' => 'name',
            '2' => 'county',
            '3' => 'quantity',
            '4' => 'organisation', //change to nr_org
        ));

        $pager = applyPaginate($volunteers, $params);

        return response()->json(array(
            "pager" => $pager,
            "data" => $volunteers->get()
        ), 200); 
    }

     /**
     * @SWG\Get(
     *   tags={"Volunteers"},
     *   path="/api/volunteers/{id}",
     *   summary="Show volunteer info ",
     *   operationId="show",
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */

    public function show($id)
    {
        return Volunteer::find($id);
    }

    /**
     * @SWG\Post(
     *   tags={"Volunteers"},
     *   path="/api/volunteers",
     *   summary="Create volunteer",
     *   operationId="store",
     *   @SWG\Parameter(
     *     name="organisation_id",
     *     in="query",
     *     description="Volunteer organisation id.",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="name",
     *     in="query",
     *     description="Volunteer name.",
     *     required=true,
     *     type="string"
     *   ),
     *  @SWG\Parameter(
     *     name="ssn",
     *     in="query",
     *     description="Volunteer ssn.",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="email",
     *     in="query",
     *     description="Volunteer email.",
     *     required=true,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="phone",
     *     in="query",
     *     description="Volunteer phone.",
     *     required=true,
     *     type="string"
     *   ),
     *  @SWG\Parameter(
     *     name="county",
     *     in="query",
     *     description="Volunteer county.",
     *     required=true,
     *     type="string"
     *   ),
     *  @SWG\Parameter(
     *     name="city",
     *     in="query",
     *     description="Volunteer city.",
     *     required=true,
     *     type="string"
     *   ),
     *  @SWG\Parameter(
     *     name="address",
     *     in="query",
     *     description="Volunteer address.",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="comments",
     *     in="query",
     *     description="Volunteer comments.",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="job",
     *     in="query",
     *     description="Volunteer job.",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Parameter(
     *     name="added_by",
     *     in="query",
     *     description="Volunteer added by.",
     *     required=false,
     *     type="string"
     *   ),
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $rules = [
            'organisation_id' => 'required',
            'email' => 'required|string|email|max:255|unique:volunteers.volunteers',
            'phone' => 'required|string|min:6|',
            'ssn' => new Cnp,
            'name' => 'required|string|max:255',
        ];
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return response(['errors' => $validator->errors()->all()], 400);
        }
        $data = convertData($validator->validated(), $rules);

        $request->has('courses') ? $data['courses'] = $request->courses : '';
        $request->has('allocation') ? $data['allocation'] = $request->allocation : '';
        $request->has('comments') ? $data['comments'] = $request->comments : '';
        $request->has('job') ? $data['job'] = $request->job : '';

        //Add Organisation
        $organisation_id = $request->organisation_id;
        $organisation = \DB::connection('organisations')->collection('organisations')
            ->where('_id', '=', $organisation_id)
            ->get(['_id', 'name', 'website'])
            ->first();
        $data['organisation'] = $organisation;

        //Add City and County
        if ($request->has('county')) {
            $county = County::query()
                ->get(['_id', 'name', 'slug'])
                ->where('_id', '=', $request->county)
                ->first()
                ->toArray();

            $data['county'] = $county ? $county : null;
        }

        if ($request->has('city')) {            
            $city = City::query()
                ->get(['_id', 'name', 'slug'])
                ->where('_id', '=', $request->city)
                ->first()
                ->toArray();

            $data['city'] = $city ?  $city : null;
        }

        //Added by
        \Auth::check() ? $data['added_by'] = \Auth::user()->_id : '';

        $volunteer = Volunteer::create($data);

        if($volunteer->courses){
            foreach ($volunteer->courses as $course) {
                $newCourse = Course::firstOrNew([
                    'volunteer_id' => $volunteer->_id,
                    'name' => $course['name'],
                    'slug' => removeDiacritics($course['name']),
                    'acredited' => $course['acredited'],
                    'obtained' => $course['obtained'],
                    'added_by' => $data['added_by'] ? $data['added_by'] : '' ,
                ]);
                $newCourse->save();
            }
        }

        return response()->json($volunteer, 201); 
    }

    /**
     * @SWG\put(
     *   tags={"Volunteers"},
     *   path="/api/volunteers/{id}",
     *   summary="Update volunteer",
     *   operationId="update",
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */

    public function update(Request $request, $id)
    {
        $volunteer = Volunteer::findOrFail($id);
        $volunteer->update($request->all());

        return $volunteer;
    }

    /**
     * @SWG\Delete(
     *   tags={"Volunteers"},
     *   path="/api/volunteers/{id}",
     *   summary="Delete volunteer",
     *   operationId="delete",
     *   @SWG\Response(response=200, description="successful operation"),
     *   @SWG\Response(response=406, description="not acceptable"),
     *   @SWG\Response(response=500, description="internal server error")
     * )
     *
     */

    public function delete(Request $request, $id)
    {
        $volunteer = Volunteer::findOrFail($id);
        $volunteer->delete();

        $response = array("message" => 'Volunteer deleted.');

        return response()->json($response, 200);
    }
}
