<?php

class LoginController extends \BaseController
{
  private $idUser;

  public function __construct()
  {
    $id = Session::get("user");
    if ($id == null || $id == "") {
      $this->idUser = false;
    } else {
      $this->idUser = Crypt::decrypt($id);
    }
    session_save_path("/tmp/");
    session_start();
  }

  public function getIndex()
  {
    return $this->getLogin();
  }

  /* cria o novo usuário */
  public function postIndex()
  {
    $user = User::whereEmail(Input::get("email"))->first();
    if (!isset($user) || $user == null) {
      $user = new User;
      $user->name = Input::get("name");
      $user->email = Input::get("email");
      $user->password = Hash::make(Input::get("password"));
      $user->cadastre = "W";
      $user->save();

      $course = new Course;
      $course->idInstitution = $user->id;
      $course->name = "Meu Curso";
      $course->absentPercent = 25;
      $course->average = 7;
      $course->averageFinal = 5;
      $course->save();

      $url = url("/check/") . "/" . Crypt::encrypt($user->id);

      Mail::send('email.welcome', ["url" => $url, "name" => $user->name, "email" => $user->email], function ($message) {
        $user = User::whereEmail(Input::get("email"))->first();
        $message->to($user->email, $user->name)
          ->subject("Seja bem-vindo");
      });

      return Redirect::to("/")->with("msg", "Um email de confirmação foi encaminhado para <b>" . Input::get("email") . "</b>");
    } else {
      return Redirect::to("/login")->with("error", "O email <b>" . Input::get("email") . "</b> já está cadastrado em nosso sistema.");
    }
  }

  /* mostra a tela de login */
  public function getLogin()
  {
    if ($this->idUser == false) {
      return View::make("user.login", []);
    } else {
      return Redirect::guest("/");
    }

  }

  /* faz o login no sistema */
  public function postLogin()
  {
    $user = User::whereEmail(Input::get("email"))->first();
    if ($user and (Hash::check(Input::get("password"), $user->password))) {
      if ($user->cadastre == "W") {
        $url = url("/check/") . "/" . Crypt::encrypt($user->id);
        Mail::send('email.welcome', ["url" => $url, "name" => $user->name], function ($message) {
          $user = User::whereEmail(Input::get("email"))->first();
          $message->to($user->email, $user->name)
            ->subject("Seja bem-vindo");
        });
        return Redirect::to("/login")->with("error", "O email <b>" . Input::get("email") . "</b> ainda não foi validado.")->withInput(Input::except("password"));
      } else {
        if ($user->type == "M" or $user->type == "N") {
          $user->type = "P";
          $user->save();
        }
        Session::put("user", Crypt::encrypt($user->id));
        Session::put("type", $user->type);
        return Redirect::guest("/");
      }
    } else {
      return Redirect::to("/login")->with("error", "Login ou senha incorretos.")->withInput(Input::except("password"));
    }
  }

  /* faz a confirmação do email */
  public function getCheck($key)
  {
    $key = Crypt::decrypt($key);
    $user = User::find($key);
    if (isset($user) && $user != null) {
      $user->cadastre = "O"; // ok
      $user->save();
      return Redirect::guest("/login");
    } else {
      return "error";
    }
  }

  public function getEmail()
  {
    Mail::send('email.welcome', ["url" => "teste.com.br/?n=asdasdasdasda", "name" => "user"], function ($message) {
      $message->to("user@gmail.com", "User")->cc("user@gmail.com")
        ->subject("Seja bem-vindo ao LibreClass Social");
    });
  }

  public function postForgotPassword()
  {
    $user = User::whereEmail(Input::get("email"))->first();
    if (!$user) {
      return Redirect::to("/login")->with("error", "Erro: Email não está cadastrado.");
    } elseif ($user->cadastre == "N" or $user->cadastre == "W") {
      $password = str_shuffle("LibreClass"); //substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5);
      $user->password = Hash::make($password);
      $user->save();

      Mail::send('email.forgot-password', ["password" => $password, "user" => $user], function ($message) {
        $user = User::whereEmail(Input::get("email"))->first();
        $message->to($user->email, $user->name)
          ->subject("LibreClass Social - Sua nova senha");
      });
      return Redirect::to("/login")->with("info", "Uma nova senha foi enviada para seu e-mail.");
    } else {
      $msg = "Erro: ";
      if ($user->cadastre == "G") {
        $msg .= "Seu login deve ser feito pelo Google.";
      } elseif ($user->cadastre == "F") {
        $msg .= "Seu login deve ser feito pelo Facebook.";
      }
      return Redirect::to("/login")->with("error", $msg);
    }

  }

  /**
   * Caso a rota seja inválida, esse controler envia para a raiz /
   * @param type $parameters
   * @return rota /
   */
  public function missingMethod($parameters = [])
  {
    return Redirect::guest("/");
  }

}
