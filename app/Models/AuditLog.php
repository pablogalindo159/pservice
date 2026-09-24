<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AuditLog extends Model {
 protected $fillable=['user_id','action','service_order_id','photo_id','metadata'];
 protected $casts=['metadata'=>'array'];
 public function user(){return $this->belongsTo(User::class);}
 public function order(){return $this->belongsTo(ServiceOrder::class,'service_order_id');}
 public function photo(){return $this->belongsTo(Photo::class,'photo_id')->withTrashed();}
}
