<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use App\Http\Requests\StoreServiceTypeRequest;
use App\Http\Requests\UpdateServiceTypeRequest;

class ServiceTypeController extends Controller
{
    public function getServiceTypes(){
        return ServiceType::all();
    }
    public function addServiceType(Request $request){
        if($request->isMethod('post')){
            $serviceType = new ServiceType();
            $serviceType->service_name = $request->service_name;
            serviceType->save();
        }
    }
    public function isServiceType($name) {
        $serviceType = ServiceType::where('service_name', $name)->first();
        if($serviceType){
            return true;
        } else{
            return false;
        }
    }
    public function getServiceTypesIDByServiceName($name){
        $data = ServiceType::where('service_name', $name)->first();

        if($data != null){
            return $data->id;

        } else {
            return 0;
        }
    }
}
