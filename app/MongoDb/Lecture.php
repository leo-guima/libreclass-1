<?php

namespace App\MongoDb;

class Lecture extends \Moloquent
{
	protected $fillable = ['teacher_id', 'offer_id'];

	public function offer()
	{
		return $this->belongsTo('App\Offer','offer_id');
	}

	public function teacher()
	{
		return $this->belongsTo('App\User','teacher_id');
	}

}
