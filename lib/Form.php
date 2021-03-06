<?php

/**
 * Elefant CMS - http://www.elefantcms.com/
 *
 * Copyright (c) 2011 Johnny Broadway
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/**
 * Form is an elegant form handling class. It provides validation of the form
 * referrer, request method, cross-site request forgery (CSRF) prevention,
 * and numerous convenience functions for validating form submissions and
 * input data. Also integrates with the `/js/jquery.verify_values.js`
 * jQuery plugin to provide matching client-side validation based on the
 * same set of rules.
 *
 * The input validation can be useful not just for form submissions,
 * but it's packaged here to keep things tidy.
 *
 * Simplest usage:
 *
 *     <?php
 *     
 *     $form = new Form ('post', $this);
 *     
 *     echo $form->handle (function ($form) {
 *         // Create some data...
 *         $foo = new MyModel ($_POST);
 *         $foo->put ();
 *     
 *         // Refer them to a thank you page
 *         $form->controller->redirect ('/thank/you');
 *     });
 *     
 *     ?>
 *
 * Long form usage:
 *
 *     <?php
 *     
 *     $form = new Form ('post', 'apps/myapp/forms/verify.php');
 *
 *     if ($form->submit ()) {
 *         // handle form
 *         info ($_POST);
 *
 *     } else {
 *         // set some default values
 *         $obj = new StdClass;
 *         $obj->foo = 'bar';
 *
 *         // merge with user input
 *         $obj = $f->merge_values ($obj);
 *
 *         // get failed fields
 *         $obj->failed = $form->failed;
 *
 *         // add scripts for client-side validation
 *         $page->add_script ('<script src="/js/jquery.verify_values.js"></script>');
 *         $page->add_script ('<script>
 *             $(function () {
 *                 $.verify_values ({
 *                     element: "#myapp-form",
 *                     handler: "myapp/verify",
 *                     callback: function (failed) {
 *                         // highlight the failed elements
 *                     }
 *                 });
 *             });
 *             </script>');
 *
 *         // output your form template
 *         echo $tpl->render ('myapp/form', $obj);
 *     }
 *     
 *     ?>
 */
class Form {
	/**
	 * Fields that failed validation.
	 */
	public $failed = array ();

	/**
	 * The required request method.
	 */
	public $method = 'post';

	/**
	 * Validation rules.
	 */
	public $rules = array ();

	/**
	 * The original `$form_rules` passed to the constructor, parsed
	 * down to the `appname/rules` short form.
	 */
	private $_rules;

	/**
	 * A view to render the form with. Used by `handle()`.
	 */
	public $view = false;

	/**
	 * A data object with the initial form values. Used by `handle()`.
	 */
	public $data = false;

	/**
	 * An optional copy of the controller object.
	 */
	public $controller = false;

	/**
	 * Whether to verify the referrer or not.
	 */
	public $verify_referrer = true;

	/**
	 * Whether to verify with a CSRF token or not.
	 */
	public $verify_csrf = true;

	/**
	 * Token generated for CSRF prevention.
	 */
	public $csrf_token;

	/**
	 * The name of the token form field.
	 */
	public $csrf_field_name = '_token_';

	/**
	 * The reason `submit()` failed to pass.
	 */
	public $error = false;

	/**
	 * Whether `handle()` should include the default JavaScript validation
	 * or just the `/js/jquery.verify_values.js` script so you can write
	 * your own custom validation display.
	 */
	public $js_validation = true;

	/**
	 * Constructor method. Parameters are:
	 *
	 * - Required request method (default is 'post')
	 * - A reference to the form rules file, or a Controller object
	 * - If param 2 is a file reference, this can be the Controller object
	 *
	 * Usage:
	 *
	 *     <?php
	 *     
	 *     $f = new Form (); // defaults and no Controller or rules set
	 *     $f = new Form ('post'); // POST requests but no Controller or rules
	 *     $f = new Form ('post', $this); // POST and Controller set
	 *     $f = new Form ('post', 'myapp/rules'); // POST and rules set
	 *     $f = new Form ('post', 'myapp/rules, $this); // Everything set
	 *     
	 *     ?>
	 *
	 * Note that if the rules are not set but the Controller is passed,
	 * the rules file will be assumed to match the appname/handlername of
	 * the currently active handler, and the view will be set to match as
	 * well. This is the most handy scenario, since if you match your
	 * rules file, handler, and view names, you can simply say:
	 *
	 *     <?php
	 *     
	 *     $f = new Form ('post', $this);
	 *     
	 *     ?>
	 *
	 * And it will set everything up correctly based on `$this->uri` in the
	 * Controller.
	 */
	public function __construct ($required_method = 'post', $form_rules = false, $controller = false) {
		// Normalize the request method to lowercase
		$this->method = strtolower ($required_method);

		if ($form_rules instanceof Controller) {
			// Controller was passed as the second param
			$this->controller = $form_rules;
			$form_rules = $this->controller->uri;
		} else {
			// Controller was third param
			$this->controller = $controller;
		}

		// Fetch any form validation rules
		if (! empty ($form_rules)) {
			if (! file_exists ($form_rules)) {
				list ($app, $form) = explode ('/', $form_rules, 2);
				$form_rules = 'apps/' . $app . '/forms/' . $form . '.php';
			}
			if (file_exists ($form_rules)) {
				$this->rules = parse_ini_file ($form_rules, true);
			}
			// Set the view by default based on the form rules (can be changed later)
			$this->_rules = preg_replace ('|apps/(.+)/forms/(.+)\.php$|', '\1/\2', $form_rules);
			$this->view = $this->_rules;
		}
	}

	/**
	 * Accepts an anonymous function (aka closure) that handles the
	 * form submission, and abstracts away the rendering of the form
	 * based on the provided rules and view, helping eliminate much
	 * of the boilerplate code of form creation.
	 *
	 * Note that `handle()` will also include `/js/jquery.verify_values.js`
	 * for you, but you need to provide your own initialization code
	 * to perform the client-side validations. There is a page in the
	 * public wiki on www.elefantcms.com describing the steps for this.
	 *
	 * Also note that if the anonymous function returns false, there
	 * will be no output and the false status will be passed to the
	 * handler to catch.
	 */
	public function handle ($func) {
		if ($this->submit ()) {
			// Form can be handled, capture output and return it
			// If the function returns false, simply pass that
			// to the handler with no output
			ob_start ();
			$res = $func ($this);
			if ($res !== false) {
				return ob_get_clean ();
			}
			ob_end_clean ();
		}

		if (! $this->view) {
			// No view so we simply return false so the handler
			// can take over rendering the form
			return false;
		}

		// Render the view and return its output
		$page = $this->controller->page ();
		$tpl = $this->controller->template ();

		// Take the initial form data from $this->data or a new StdClass
		if ($this->data === false) {
			$o = new StdClass;
		} else {
			$o = is_object ($this->data) ? $this->data : (object) $this->data;
		}

		// Determine the default values
		foreach ($this->rules as $field => $rules) {
			foreach ($rules as $key => $value) {
				if ($key === 'default') {
					$o->{$field} = $value;
					break;
				}
			}
		}

		// Set some views to go to the template
		$o = $this->merge_values ($o);
		$o->_form = str_replace ('/', '-', $this->view) . '-form';
		$o->_failed = $this->failed;
		$o->_rules = $this->_rules;
		$page->add_script ('/js/jquery.verify_values.js');
		if ($this->js_validation) {
			return $tpl->render ('admin/default-validation', $o) . $tpl->render ($this->view, $o);
		}
		return $tpl->render ($this->view, $o);
	}

	/**
	 * Check if the form is okay to submit. Verifies the request method,
	 * the referrer, and the input data.
	 */
	public function submit () {
		$values = ($this->method === 'post') ? $_POST : $_GET;

		$this->initialize_csrf ();
		
		if (! $this->verify_request_method ()) {
			// form hasn't been submitted yet, or request method doesn't match
			$this->error = 'Request method must be ' . strtoupper ($this->method);
			return false;
		}
		
		if ($this->verify_referrer && ! $this->verify_referrer ()) {
			$this->error = 'Referrer must match the host name.';
			return false;
		}

		if ($this->verify_csrf && ! $this->verify_csrf ()) {
			$this->error = 'Cross-site request forgery detected.';
			return false;
		}

		$this->failed = $this->verify_values ($values, $this->rules);
		if (count ($this->failed) > 0) {
			$this->error = 'Validation error.';
			return false;
		}
		return true;
	}

	/**
	 * Merge the values from `$_GET` or `$_POST` onto a data array or
	 * object for re-rendering a form with the latest data entered.
	 */
	public function merge_values ($obj) {
		$values = ($this->method === 'post') ? $_POST : $_GET;
		
		foreach ($values as $k => $v) {
			if (is_object ($obj)) {
				$obj->{$k} = $v;
			} else {
				$obj[$k] = $v;
			}
		}

		return $obj;
	}

	/**
	 * Verify the request method is the one specified.
	 */
	public function verify_request_method () {
		if (strtolower ($_SERVER['REQUEST_METHOD']) !== $this->method) {
			return false;
		}
		return true;
	}

	/**
	 * Verify the referrer came from this site. No remote form submissions,
	 * since those are almost certainly abusive.
	 */
	public function verify_referrer () {
		if (strpos ($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false) {
			return false;
		}
		return true;
	}

	/**
	 * Initialize the CSRF token.
	 */
	public function initialize_csrf () {
		if ($this->verify_csrf) {
			// Start a session
			@session_set_cookie_params (time () + 2592000);
			@session_start ();

			if (isset ($_SESSION['csrf_token']) && $_SESSION['csrf_expires'] > time ()) {
				// Get an existing token
				$this->csrf_token = $_SESSION['csrf_token'];

				// Reset the timer on the request so it doesn't expire on the
				// user if time is running short
				$_SESSION['csrf_expires'] = time () + 7200;
			} else {
				// Generate a random token
				$this->csrf_token = md5 (uniqid (rand (), true));

				// Set the token and expiry time (2 hours)
				$_SESSION['csrf_token'] = $this->csrf_token;
				$_SESSION['csrf_expires'] = time () + 7200;
			}

			// Append the CSRF token Javascript if there is a page object
			if (isset ($GLOBALS['page'])) {
				$GLOBALS['page']->add_script (
					$this->generate_csrf_script (),
					'tail'
				);
			}
		}
	}

	/**
	 * Generate the script that will append the token to forms in the page.
	 * You do not need to call this directly as long as you have `{{ tail|none }}`
	 * in your layout template, since `initialize_csrf()` will automatically
	 * add this to the tail if it can.
	 */
	function generate_csrf_script () {
		return sprintf (
			'<script>$(function(){$("form").append("<input type=\'hidden\' name=\'%s\' value=\'%s\'/>");});</script>',
			$this->csrf_field_name,
			$this->csrf_token
		);
	}

	/**
	 * Verify the CSRF token is present, matches the stored value in the session
	 * data, and has not expired (2 hour limit).
	 */
	public function verify_csrf () {
		if (! isset ($_SESSION['csrf_token']) || ! isset ($_SESSION['csrf_expires'])) {
			// No token in session
			return false;
		}

		$values = ($this->method === 'post') ? $_POST : $_GET;

		if (! isset ($values[$this->csrf_field_name])) {
			// No token provided
			return false;
		}

		if ($_SESSION['csrf_token'] !== $values[$this->csrf_field_name]) {
			// Token doesn't match
			return false;
		}
		if ($_SESSION['csrf_expires'] < time ()) {
			// Timed out
			return false;
		}
		return true;
	}

	/**
	 * Alias of `Validator::validate()`.
	 */
	public static function verify_value ($value, $type, $validator = false) {
		return Validator::validate ($value, $type, $validator);
	}
	
	/**
	 * Alias of `Validator::validate_list()`.
	 */
	public function verify_values ($values, $validations = array ()) {
		return Validator::validate_list ($values, $validations);
	}
}

?>