<?php
namespace App\Http\Controllers;
use App\Models\BusinessReview;
use App\Services\BusinessReviews;
use Illuminate\Http\Request;
class BusinessReviewController extends Controller
{
    private function review(string $token): BusinessReview {
        return BusinessReview::where('token',$token)->whereHas('owner',fn($q)=>$q->where('type','owner'))->firstOrFail();
    }
    public function show(string $token) {
        $review=$this->review($token);
        $company=BusinessReviews::company($review->owner_id);
        return response()->view('reviews.public',compact('review','company'))->header('Cache-Control','private, no-store')
            ->header('Referrer-Policy','no-referrer')->header('X-Robots-Tag','noindex, nofollow, noarchive')
            ->header('Content-Security-Policy',"default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    }
    public function store(Request $request, string $token) {
        $review=$this->review($token);
        $data=$request->validate(['rating'=>'required|integer|between:1,5']);
        // Atomic conditional update: two tabs or simultaneous requests cannot overwrite a rating.
        $saved=BusinessReview::whereKey($review->id)->whereNull('rating')->update(['rating'=>$data['rating'],'rated_at'=>now()]);
        return redirect()->route('reviews.show',$token)->with($saved ? 'success' : 'info', $saved ? 'Değerlendirmeniz kaydedildi. Teşekkür ederiz.' : 'Bu işletme için değerlendirmeniz zaten kayıtlı.');
    }
    public function index(Request $request) {
        abort_unless(isBusinessOwner() || auth()->user()->type==='super admin',403);
        $isAdmin=auth()->user()->type==='super admin';
        $data=$request->validate(['owner_id'=>'nullable|integer|min:1']);
        $query=BusinessReview::query()->when(!$isAdmin,fn($q)=>$q->where('owner_id',parentId()))
            ->when($isAdmin && !empty($data['owner_id']),fn($q)=>$q->where('owner_id',$data['owner_id']));
        $count=(clone $query)->whereNotNull('rating')->count();
        $average=(clone $query)->avg('rating');
        $reviews=$query->with('owner:id,name')->latest('id')->paginate(25)->withQueryString();
        return view('reviews.index',compact('reviews','count','average','isAdmin'));
    }
}
