<?php
defined('BASEPATH') or exit('No direct script access allowed');

/* On your database, open a SQL terminal paste this and execute: */
// CREATE TABLE IF NOT EXISTS `users` (
//   `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
//   `username` varchar(255) NOT NULL DEFAULT '',
//   `email` varchar(255) NOT NULL DEFAULT '',
//   `password` varchar(255) NOT NULL DEFAULT '',
//   `avatar` varchar(255) DEFAULT 'default.jpg',
//   `created_at` datetime NOT NULL,
//   `updated_at` datetime DEFAULT NULL,
//   `is_admin` tinyint(1) unsigned NOT NULL DEFAULT '0',
//   `is_confirmed` tinyint(1) unsigned NOT NULL DEFAULT '0',
//   `is_deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
//   PRIMARY KEY (`id`)
// );
// CREATE TABLE IF NOT EXISTS `ci_sessions` (
//   `id` varchar(40) NOT NULL,
//   `ip_address` varchar(45) NOT NULL,
//   `timestamp` int(10) unsigned DEFAULT 0 NOT NULL,
//   `data` blob NOT NULL,
//   PRIMARY KEY (id),
//   KEY `ci_sessions_timestamp` (`timestamp`)
// );

/**
 * User class.
 * 
 * @extends REST_Controller
 */
require(APPPATH . '/libraries/REST_Controller.php');

use Restserver\Libraries\REST_Controller;

class User extends REST_Controller
{

	/**
	 * __construct function.
	 * 
	 * @access public
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
		$this->load->library('Authorization_Token');
		$this->load->model('user_model');
		$this->load->config('jwt');
	}

	/**
	 * register function.
	 * 
	 * @access public
	 * @return void
	 */
	public function register_post()
	{

		// set validation rules
		$this->form_validation->set_rules('username', 'Username', 'trim|required|alpha_numeric|min_length[4]|is_unique[users.username]', array('is_unique' => 'This username already exists. Please choose another one.'));
		$this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email|is_unique[users.email]');
		$this->form_validation->set_rules('password', 'Password', 'trim|required|min_length[6]');
		//$this->form_validation->set_rules('password_confirm', 'Confirm Password', 'trim|required|min_length[6]|matches[password]');

		if ($this->form_validation->run() === false) {

			// validation not ok, send validation errors to the view
			$this->response([
				'message' => 'Validation rules violated',
				'errors' => $this->form_validation->error_array()
			], REST_Controller::HTTP_BAD_REQUEST);
		} else {

			// set variables from the form
			$username = $this->input->post('username');
			$email    = $this->input->post('email');
			$password = $this->input->post('password');

			if ($res = $this->user_model->create_user($username, $email, $password)) {

				// user creation ok
				$token_data['uid'] = $res;
				$token_data['username'] = $username;
				$tokenData = $this->authorization_token->generateToken($token_data);

				$final = [
					'message' => 'Success register',
					'data' => [
						'user' => [
							'id' => $res,
							'name' => $username,
							'email' => $email,
							'image' => base_url('public/images/default.jpg')
						],
						'auth' => [
							'token' => $tokenData,
							'type' => 'Bearer',
							'expires_at' => Date('Y-m-d H:i:s', time() + $this->config->item('token_expire_time'))
						]
					]
				];

				$this->response($final, REST_Controller::HTTP_OK);
			} else {

				// user creation failed, this should never happen
				$this->response([
					'message' => 'There was a problem creating your new account. Please try again.',
					'errors' => []
				], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
			}
		}
	}

	/**
	 * login function.
	 * 
	 * @access public
	 * @return void
	 */
	public function login_post()
	{

		// set validation rules
		$this->form_validation->set_rules('username', 'Username', 'required|alpha_numeric');
		$this->form_validation->set_rules('password', 'Password', 'required');

		if ($this->form_validation->run() == false) {

			// validation not ok, send validation errors to the view
			$this->response(['Validation rules violated'], REST_Controller::HTTP_OK);
		} else {

			// set variables from the form
			$username = $this->input->post('username');
			$password = $this->input->post('password');

			if ($this->user_model->resolve_user_login($username, $password)) {

				$user_id = $this->user_model->get_user_id_from_username($username);
				$user    = $this->user_model->get_user($user_id);

				// set session user datas
				$_SESSION['user_id']      = (int)$user->id;
				$_SESSION['username']     = (string)$user->username;
				$_SESSION['logged_in']    = (bool)true;
				$_SESSION['is_confirmed'] = (bool)$user->is_confirmed;
				$_SESSION['is_admin']     = (bool)$user->is_admin;

				// user login ok
				$token_data['uid'] = $user_id;
				$token_data['username'] = $user->username;
				$tokenData = $this->authorization_token->generateToken($token_data);
				$final = array();

				$final['message'] = 'Success login';
				$final['data'] = [
					'user' => [
						'id' => $user->id,
						'name' => $user->username,
						'email' => $user->email,
						'image' => base_url('public/images/' . $user->avatar)
					],
					'auth' => [
						'token' => $tokenData,
						'type' => 'Bearer',
						'expires_at' => Date('Y-m-d H:i:s', time() + $this->config->item('token_expire_time'))
					]
				];

				$this->response($final, REST_Controller::HTTP_OK);
			} else {

				// login failed
				$this->response([
					'message' => 'Wrong username or password.',
					'errors' => []
				], REST_Controller::HTTP_BAD_REQUEST);
			}
		}
	}

	/**
	 * logout function.
	 * 
	 * @access public
	 * @return void
	 */
	public function logout_post()
	{

		if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {

			// remove session datas
			foreach ($_SESSION as $key => $value) {
				unset($_SESSION[$key]);
			}

			// user logout ok
			$this->response([
				'message' => 'Success logout',
				'data' => null
			], REST_Controller::HTTP_OK);
		} else {

			// there user was not logged in, we cannot logged him out,
			// redirect him to site root
			// redirect('/');
			$this->response([
				'message' => 'User not logged in',
				'errors' => []
			], REST_Controller::HTTP_BAD_REQUEST);
		}
	}
}
