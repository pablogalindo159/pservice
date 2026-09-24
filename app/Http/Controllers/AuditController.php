<?php
namespace App\Http\Controllers;
use App\Models\AuditLog;use Illuminate\Http\Request;
class AuditController extends Controller{public function index(Request $request){abort_unless($request->user()?->canAudit(),403);$q=$request->string('q')->trim()->toString();$logs=AuditLog::with(['user','order'])->when($q,fn($x)=>$x->where('action','like',"%$q%")->orWhereHas('user',fn($u)=>$u->where('name','like',"%$q%"))->orWhereHas('order',fn($o)=>$o->where('number','like',"%$q%")))->latest()->paginate(40)->withQueryString();return view('audit.index',compact('logs','q'));}}
