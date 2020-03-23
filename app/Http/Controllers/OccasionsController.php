<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use \App\Occasion;

use Illuminate\Support\Facades\Auth;

use Illuminate\Support\Facades\DB;


class OccasionsController extends Controller
{
    public function index(){

        $user = auth()->user();

        $occasions1 = DB::table('occasions')->paginate(12);

        $occasions = DB::table('occasions')->select(DB::raw('min(id) as id, name, street, city, min(start) as start, user_name, category'))
            ->groupBy('name', 'user_name', 'street', 'city', 'category')
            ->paginate(12);

        return view('occasions.index', compact('user', 'occasions'));
    }

    public function create(){

        $user = auth()->user();
        if($user){
            $hangouts = $user->hangout->getTableColumns();
            $sports = $user->sport->getTableColumns();
            $categories = array_merge($hangouts, $sports);

            $exclude = array("user_id", "id", "updated_at", "created_at");

            $category = array_diff($categories, $exclude);

            $days = ['Monday', 'Tuesday', 'Wendsday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];


            return view('occasions.create', compact('category', 'days'));
        }
        return view('occasions.create');


    }


    public function store(){

        $data = request()->validate([
            'name'=> 'required|min:3',
            'street'=> 'required|min:3',
            'city'=> 'required|min:3',
            'zipcode'=> 'required|min:3',
            'when'=> 'required',
            'category'=> 'required|min:3',
            'max_people'=> 'required|numeric|min:2',
        ]);
        unset($data['when']);

        $user = auth()->user();

        //multiple days and times
        if (request('when') == '1'){
            $ntime = 1;
            //if we have multiple times
            if(is_array(request('time-start'))){
                $ntime = sizeof(request('time-start'));
                $validation = [
                    'day' => 'required',
                    'start'=> 'required|date|after:today',
                ];
                for ($i = 1; $i <= $ntime; $i++){
                    $validation = array_merge($validation,['time-start['.$i.']' => 'required'], [
                        'time-end['.$i.']' => 'required|after:time-start'.$i], ['day'.$i => 'required']);
                }
                //dd($validation);

            } else { //for one time
                $validation = [
                    'day' => 'required',
                    'start' => 'required|date|after:today',
                    'time-start' => 'required',
                    'time-end' => 'required|after:time-start',
                    'day' => 'required',
                ];
                // dd("oke");
            }
            //$when = request()->validate($validation);

            $timezone = date_default_timezone_get();
            date_default_timezone_set($timezone);

            $startdate = strtotime(request('start'));
            $enddate = $startdate + (request('repeat') * 86400);

            for ($i = 1; $i <= $ntime; $i = $i + 1){

                $checkedDays = request('day'.$i);
                //dd($checkedDays);

                $stime = request('time-start')[$i];
                $etime = request('time-end')[$i];

                //dd(request('start'));

                for ($d = $startdate; $d <= $enddate; $d = $d + 86400) {
                    $today = date("N", $d);
                    foreach ($checkedDays as $day => $value) {
                        if ($today == $value + 1) {
                            $sdate = date('Y-m-d', $d) . ' ' . $stime;
                            $edate = date('Y-m-d', $d) . ' ' . $etime;

                            //dd($sdate);
                            $occasion = Occasion::create(array_merge($data, ['start' => $sdate], ['end' => $edate], ['user_name' => $user->name]));
                            $occasion->users()->syncWithoutDetaching($user->id);
                        }

                    }
                }

            }


        } else {

            $when = request()->validate([
                'start-one'=> 'required|date|after:today',
                'end-one'=> 'required|date|',
                'time-start-one'=> 'required',
                'time-end-one'=> 'required',
            ]);


            $startdate = request('start-one') .' ' . request('time-start-one');
            //dd($startdate);

            $enddate = request('end-one') .' '. request('time-end-one');;
            //dd($when);
            $occasion = Occasion::create(array_merge($data, ['start'  =>  $startdate], ['end'  =>  $enddate], ['user_name'  =>  $user->name]));

            $occasion->users()->syncWithoutDetaching($user->id);
        }




        return redirect('events');
    }

    public static function showDataForModal($occasion){
        $time =  DB::table('occasions')->where('name', $occasion->name)->get();
        //dd($time);
        return $time;
    }

    public static function wall(Occasion $occasion){

        $user = auth()->user();
        $occasion->users()->syncWithoutDetaching($user->id);
        $joined = DB::table('users')
            ->join('occasion_user', 'users.id', '=', 'occasion_user.user_id')
            ->where('occasion_user.occasion_id', $occasion->id)
            ->select('users.name')
            ->get();

        //dd($joined);
        return view('occasions.wall', compact('joined', 'occasion'));
    }
}
