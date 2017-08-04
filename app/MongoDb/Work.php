<?php

namespace App\MongoDb;

class Work extends \Moloquent
{
	protected $hidden = ['_id'];
	protected $fillable = ['institution_id', 'teacher_id', 'register'];

	public function teacher()
	{
		return $this->belongsTo('App\MongoDb\User');
	}

	public function institution()
	{
		return $this->belongsTo('App\MongoDb\User');
	}
}
