<?php

namespace App\Http\Controllers;

use App\Models\CompostEntry;
use App\Models\CompostProducer;
use App\Models\Crop;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Subscription;
use App\Models\PickupSchedule;

class FarmerController extends Controller
{
    // public function index()
    // {
    //     $farmerId = auth()->user()->farmer->user_id;

    //     $compostSchedules = PickupSchedule::where(function ($query) use ($farmerId) {
    //         $query->where('RecipientFarmerID', $farmerId)
    //             ->where('PickupType', 'Compost Delivery');
    //     })->orderBy('ScheduledDate')->get();

    //     $cropSchedules = PickupSchedule::where(function ($query) use ($farmerId) {
    //         $query->where('SenderFarmerID', $farmerId)
    //             ->where('PickupType', 'Waste Pickup');
    //     })->orderBy('ScheduledDate')->get();

    //     return view("farmerrmain", compact('compostSchedules', 'cropSchedules'));
    // }

    public function index()
{
    $userId = auth()->user()->id;

    $pickup = PickupSchedule::where('SenderFarmerID', $userId)
                ->where('PickupType', 'Crops Delivery')
                ->orderBy('ScheduledDate', 'asc')
                ->get();

    $delivery = PickupSchedule::where('RecipientFarmerID', $userId)
                ->where('PickupType', 'Compost Drop Off')
                ->orderBy('ScheduledDate', 'asc')
                ->get();

    $data = Subscription::where('SubscriberID', $userId)
                ->where('Status', '<>', 'Expired')
                ->get();

    return view('farmerrmain', compact('pickup', 'delivery', 'data'));
}


    public function subscribeToProducers(Request $request)
    {
        $farmer = auth()->user()->farmer;

        if (!$farmer) {
            return redirect()->back()->with('error', 'You do not have access to this section.');
        }

        $request->validate([
            'producer_ids' => 'required|array',
            'producer_ids.*' => 'exists:compost_producer,user_id',
        ]);

        $farmer->compostProducers()->sync($request->producer_ids);

        return redirect()->route('producers.index')->with('success', 'Subscriptions updated successfully.');
    }

    public function details($composterId, $compostId)
    {
        $farmer = Farmer::where('user_id', Auth::id())->firstOrFail();
        $totalPoints = $farmer->totalPoints();

        $compostEntry = CompostEntry::with(['priceList', 'compostProducer'])
            ->where('compost_producer_id', $composterId)
            ->findOrFail($compostId);

        return view('composters.show-detail', compact('compostEntry', 'totalPoints'));
    }


    public function showPoints()
    {
        $farmer = Farmer::where('user_id', Auth::id())->firstOrFail();

        $totalPoints = $farmer->totalPoints();

        return view('farmer.points', compact('totalPoints'));
    }

    public function subsManagementResume(Request $req)
    {
        $temp = Subscription::where("SubscriptionID", $req->subscriptionID)->first();
        $temp->update([
            "Status" => "Active"
        ]);
        return redirect()->back();
    }
    public function subsManagementPause(Request $req)
    {
        $temp = Subscription::where("SubscriptionID", $req->subscriptionID)->first();
        // dd($req);
        $temp->update([
            "Status" => "Postponed"
        ]);

        return redirect()->back();
    }

    public function subsManageCancel(Request $req)
    {
        $subscription = Subscription::findOrFail($req->subscriptionID);

        $subscription->delete();

        return redirect()->back()->with('success', 'Subscription has been canceled');
    }

    public function indexFarmer(Request $request)
    {
        $query = Farmer::query();

        if ($request->filled('name')) {
            $query->where('Name', 'like', '%' . $request->input('name') . '%');
        }

        $farmers = $query->get();

        return view('farmers.index', compact('farmers'));
    }

    public function showFarmer($farmerId)
    {
        $farmer = Farmer::findOrFail($farmerId);
        $crops = Crop::where('farmer_id', $farmerId)->get();

        return view('farmers.show', compact('farmer', 'crops'));
    }

    public function detailsFarmer($farmerId, $cropId)
    {
        $user = auth()->user();
        $totalPoints = 0;

        if ($user->role === "restaurant_owner") {
            $totalPoints = $user->restaurantOwner->PointsBalance ?? 0;
        }

        $farmer = Farmer::findOrFail($farmerId);

        $crop = Crop::with('priceList')->findOrFail($cropId);

        return view('farmers.show-detail', compact('farmer', 'crop', 'totalPoints'));
    }
}
