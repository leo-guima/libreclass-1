<?php

namespace App\Http\Controllers;

use App\MySql\User;
use App\MySql\Course;
use App\MySql\Period;
use App\MySql\Offer;
use App\MySql\Discipline;
use App\MySql\Classe;
use App\MySql\Unit;
use App\MySql\Attend;

use Session;
use Crypt;
use Redirect;

class ClassesController extends Controller
{
	// public function postListdisciplines()
	// {
	// 	if (Input::has("flag")) {
	// 		$offers = Offer::where("idClass", Crypt::decrypt(Input::get("classe_id")))->get();
	// 		$registered_disciplines_ids = [];
	// 		foreach ($offers as $offer) {
	// 			$registered_disciplines_ids[] = $offer->idDiscipline;
	// 		}
	// 		$disciplines = Discipline::where("idPeriod", Crypt::decrypt(Input::get("period_id")))->whereStatus('E')->whereNotIn('id', $registered_disciplines_ids)->get();
	// 	} else {
	// 		$disciplines = Discipline::where("idPeriod", Crypt::decrypt(Input::get("period")))->whereStatus('E')->get();
	// 	}
	// 	return view("modules.disciplines.listOffer", ["disciplines" => $disciplines]);
	// }

	public function save(Request $in)
	{
		if (!isset($in->period_id) || !isset($in->name || !isset($in->class))){
			return ['status'=>0, 'message'=>'Dados incompletos'];
		}

		$period = Period::find(Crypt::decrypt($in->period_id));
		if (!$period){
			return ['status'=>0, 'message'=>'Período não encontrado'];
		}
		if ($period->course->institution_id != auth()->id()){
			return ['status'=>0, 'message'=>'Operação não autorizada'];
		}

		if (isset($in->classe_id)) {//Edição
			$classe = Classe::find(Crypt::decrypt($in->classe_id));
		} else {
			$classe = new Classe;
		}
		$classe->period_id = $period->id;
		$classe->name = $in->name;
		$classe->status = 'E';
		$classe->save();
		$classe->id = Crypt::encrypt($classe->id);

		return ['status'=>1, 'classe'=>$classe];
	}
	// public function postNew()
	// {
	// 	$class = new Classe;
	// 	$class->idPeriod = Crypt::decrypt(Input::get("period"));
	// 	$class->name = Input::get("name");
	// 	$class->class = Input::get("class");
	// 	$class->status = 'E';
	// 	$class->save();
	// 	foreach (Input::all() as $key => $value) {
	// 		if (strstr($key, "discipline_") != false) {
	// 			$offer = new Offer;
	// 			$offer->idClass = $class->id;
	// 			$offer->idDiscipline = Crypt::decrypt($value);
	// 			$offer->save();
	// 			$unit = new Unit;
	// 			$unit->IdOffer = $offer->id;
	// 			$unit->value = "1";
	// 			$unit->calculation = "A";
	// 			$unit->save();
	// 		}
	// 	}
	// 	return Redirect::guest("classes")->with("success", "Turma criada com sucesso!");
	// }

	// public function getInfo()
	public function read(Request $in)
	{
		if (!isset($in->classe_id)){
			return ['status'=>0, 'message'=>'Dados incompletos'];
		}

		$classe = Classe::find(Crypt::decrypt($in->classe_id));
		if (!$classe){
			return ['status'=>0, 'message'=>'Turma não encontrada'];
		}
		if ($classe->period->course->institution_id != auth()->id()){
			return ['status'=>0, 'message'=>'Operação não autorizada'];
		}

		$classe->id = Crypt::encrypt($classe->id);

		return ['status'=>1, 'classe'=>$classe];
	}

	public function delete(Request $in)
	{
		if (!isset($in->classe_id)){
			return ['status'=>0, 'message'=>'Dados incompletos'];
		}

		$classe = Classe::find(Crypt::decrypt($in->classe_id));
		if (!$classe){
			return ['status'=>0, 'message'=>'Turma não encontrada'];
		}
		if ($classe->period->course->institution_id != auth()->id()){
			return ['status'=>0, 'message'=>'Operação não autorizada'];
		}

		if ($classe->offers()->count()){
			return ['status'=>0, 'message'=>'Não foi possível excluir. Turma já vinculada a disciplina(s)'];
		}

		$classe->delete();

		return ['status'=>1];
	}

	public function postChangeStatus()
	{
		$id = Crypt::decrypt(Input::get("key"));

		$class = Classe::find($id);
		if ($class) {
			$class->status = Input::get("status");
			$class->save();
			if ($class->status == "E") {
				return Redirect::guest("/classes")->with("success", "Turma ativada com sucesso!");
			} else {
				return Redirect::guest("/classes")->with("success", "Turma bloqueada com sucesso!<br/>Turmas bloqueadas são movidas para o final.");
			}

		} else {
			return Redirect::guest("/classes")->with("error", "Não foi possível realizar essa operação!");
		}

	}

	// public function anyListOffers()
	// {
	// 	$offers = Offer::where("idClass", Crypt::decrypt(Input::get("class")))->get();
	// 	$idStudent = Crypt::decrypt(Input::get("student"));
	//
	// 	foreach ($offers as $offer) {
	// 		$offer->status = DB::select("SELECT count(*) as qtd FROM Units, Attends " .
	// 			"WHERE Units.idOffer=? AND Units.id=Attends.idUnit AND Attends.idUser=?",
	// 			[$offer->id, $idStudent])[0]->qtd;
	//
	// 		$offer->name = Discipline::find($offer->idDiscipline)->name;
	// 		$offer->id = Crypt::encrypt($offer->id);
	// 	}
	//
	// 	return $offers;
	// }

	/**
	 * Faz uma busca por todos os cursos da instituição e suas unidades ativas
	 *
	 *
	 * @return json com cursos e unidades
	 */
	public function postListUnits($status = 1)
	{
		$status = ((int) $status ? "E" : "D");

		$courses = Course::where("idInstitution", $this->idUser)->whereStatus("E")->get();
		foreach ($courses as $course) {
			$course->units = DB::select("SELECT Units.value
																		 FROM Periods, Classes, Offers, Units
																		WHERE Periods.idCourse=?
																					AND Periods.id=Classes.idPeriod
																					AND Classes.id=Offers.idCLass
																					AND Classes.status='E'
																					AND Offers.id=Units.idOffer
																					AND Units.status=?
																 GROUP BY Units.value", [$course->id, $status]);

			$course->id = Crypt::encrypt($course->id);
		}

		return $courses;
	}

	public function postBlockUnit()
	{
		$course = Course::find(Crypt::decrypt(Input::get("course")));
		if ($course->idInstitution != $this->idUser) {
			throw new Exception('Usuário inválido');
		}

		$periods = Period::where("idCourse", $course->id)->get();
		foreach ($periods as $period) {
			$classes = Classe::where("idPeriod", $period->id)->get();
			foreach ($classes as $class) {
				$offers = Offer::where("idClass", $class->id)->get();
				foreach ($offers as $offer) {
					Unit::where("idOffer", $offer->id)->whereValue(Input::get("unit"))->whereStatus("E")->update(array('status' => "D"));
				}

			}
		}
	}

	public function postUnblockUnit()
	{
		$course = Course::find(Crypt::decrypt(Input::get("course")));
		if ($course->idInstitution != $this->idUser) {
			throw new Exception('Usuário inválido');
		}

		$periods = Period::where("idCourse", $course->id)->get();
		foreach ($periods as $period) {
			$classes = Classe::where("idPeriod", $period->id)->get();
			foreach ($classes as $class) {
				$offers = Offer::where("idClass", $class->id)->get();
				foreach ($offers as $offer) {
					Unit::where("idOffer", $offer->id)->whereValue(Input::get("unit"))->whereStatus("D")->update(array('status' => "E"));
				}

			}
		}
	}

	public function anyCreateUnits()
	{
		$s_attends = false;
		$course = Course::find(Crypt::decrypt(Input::get("course")));
		if ($course->idInstitution != $this->idUser) {
			throw new Exception("Você não tem permissão para realizar essa operação");
		}

		$offers = DB::select("SELECT Offers.id FROM Periods, Classes, Offers "
			. "WHERE Periods.idCourse=? AND Periods.id=Classes.idPeriod AND Classes.id=Offers.idClass", [$course->id]);

		if (!count($offers)) {
			throw new Exception("Não possui ofertas nesse curso.");
		}

		foreach ($offers as $offer) {
			$old = Unit::where("idOffer", $offer->id)->orderBy("value", "desc")->first();

			$unit = new Unit;
			$unit->idOffer = $old->idOffer;
			$unit->value = $old->value + 1;
			$unit->calculation = $old->calculation;
			$unit->save();

			$attends = Attend::where("idUnit", $old->id)->get();

			$s_attends = false;
			foreach ($attends as $attend) {
				if (!$s_attends) {
					$s_attends = "INSERT IGNORE INTO Attends (idUnit, idUser) VALUES ($unit->id, $attend->idUser)";
				} else {
					$s_attends .= ", ($unit->id, $attend->idUser)";
				}

				//	$new = new Attend;
				//	$new->idUnit = $unit->id;
				//	$new->idUser = $attend->idUser;
				//	$new->save();
			}
			if ($s_attends) {
				DB::insert($s_attends);
			}

		}
	}
}