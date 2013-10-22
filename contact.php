<?php use \PHPMailer;

/**
 * Simple contact form for the micro CMS Pico.
 *
 * @author Klas Gidlöv
 * @link http://gidlov.com/code/
 * @license LGPL
 */

define('CONTACT_MESSAGE', '<!--CONTACT-MESSAGE-->');

class Contact {

	private $validation;
	private $message;
	private $error;
	private $post;

	public function config_loaded(&$settings) {
		// Missing config settings.
		if (empty($settings['contact']))
			return;
		// No post request.
		if (empty($settings['contact']['post']))
			return;
		$this->contact = $settings['contact'];
		$this->post = $settings['contact']['post'];

		// Post to this form was made.
		if (isset($this->post['contact']) AND $this->post['contact'] == 'true') {
			foreach (array('name', 'mail', 'message') as $value) {
				if ($value == 'mail') {
					if (filter_var($this->post['mail'], FILTER_VALIDATE_EMAIL) === false) {
						$this->validation[$value] = isset($this->contact['validation_messages']['invalid_mail']) ? sprintf($this->contact['validation_messages']['invalid_mail'], $value) : "A valid {$value} i required.";;
					}
				}
				if (empty($this->post[$value])) {
					$this->validation[$value] = isset($this->contact['validation_messages']['required']) ? sprintf($this->contact['validation_messages']['required'], $value) : "The {$value}-field is required.";
				}
			}
		}
		// No validation validation, proceed sending the email.
		if (count($this->validation) == 0) {
			$mail = new \PHPMailer;
			if (isset($this->contact['smtp'])) {
				$mail->isSMTP();
				$mail->Host = $this->contact['smtp']['host'] ? $this->contact['smtp']['host'] : '';
				$mail->SMTPAuth = $this->contact['smtp']['auth'] ? $this->contact['smtp']['auth'] : '';
				$mail->Username = $this->contact['smtp']['username'] ? $this->contact['smtp']['username'] : '';
				$mail->Password = $this->contact['smtp']['password'] ? $this->contact['smtp']['password'] : '';
				$mail->SMTPSecure = $this->contact['smtp']['encryption'] ? $this->contact['smtp']['encryption'] : '';
				$mail->Port = $this->contact['smtp']['port'] ? $this->contact['smtp']['port'] : '';
			}
			$mail->CharSet = "UTF-8";
			$mail->FromName = $this->post['name'];
			$mail->From = $this->post['mail'];
			$mail->addAddress($this->contact['send_to']);
			$subject = isset($this->post['subject']) ? $this->post['subject'] : '';
			$args = array($this->post['name'], $this->post['mail'], $subject, $settings['site_title'], $settings['base_url']);
			$header = isset($this->contact['body_header']) ? vsprintf($this->contact['body_header'], $args) : '';
			$footer = isset($this->contact['body_footer']) ? vsprintf($this->contact['body_footer'], $args) : '';
			if (isset($this->contact['subject'])) {
				$mail->Subject = vsprintf($this->contact['subject'], $args);
			} elseif ($subject != '') {
				$mail->Subject = $this->post['subject'];
			}
			$mail->Body = $header.$this->post['message'].$footer;
			
			if(!$mail->send()) {
				// Try to use SMTP if you'll get here.
				$this->error = isset($mail->validationInfo) ? $mail->validationInfo : 'Unknown mailing error.';
			} else {
				$this->post = false;
				$this->message = true;
			}
		}
	}

	public function content_parsed(&$content) {
		// Show validation failiurs.
		if (isset($this->validation)) {
			$validation = '';
				foreach ($this->validation as $section => $value) {
					if (isset($value) AND $value != '') {
						if (isset($this->contact['error_class'])) {
							$content = preg_replace('/<input(.*?name="'.$section.'".*?class=".*?)"/ms', '<input$1 '.$this->contact['error_class'].'"', $content);
						}
						$validation .= $value."<br />\n";
					}
				}
			if ($validation) {
				$message = isset($this->contact['alert_messages']['validation_error']) ? $this->contact['alert_messages']['validation_error'] : '<div class="alert alert-danger"><h4>Validation Failed!</h4><p>%1$s</p></div>';
				$content = preg_replace('/'.CONTACT_MESSAGE.'/ms', sprintf($message, $validation), $content);
			}
		}
		if ($this->message) {
			$message = isset($this->contact['alert_messages']['success']) ? $this->contact['alert_messages']['success'] : '<div class="alert alert-success"><h2>Thanks for your message!</h2><p>I will reply as soon as possible.</p></div>';
			$content = preg_replace('/'.CONTACT_MESSAGE.'/ms', $message, $content);
		}
		if ($this->error) {
			$message = isset($this->contact['alert_messages']['error']) ? $this->contact['alert_messages']['error'] : '<div class="alert alert-danger"><h2>Whops, error!</h2><p>Your message could not be sent. Sorry. %1$s</p></div>';
			$content = preg_replace('/'.CONTACT_MESSAGE.'/ms', sprintf($message, $this->error), $content);
		}
		// User input.
		if (empty($this->post))
			return;
		foreach ($this->post as $key => $value) {
			if ($key == 'message') {
				$content = preg_replace('/<textarea(.*?)><\/textarea>/', '<textarea$1>'.$value.'</textarea>', $content);	
			} else {
				$content = preg_replace('/<input(.*?)name="'.$key.'"/ms', "<input$1name='{$key}' value='{$value}'", $content);
			}
		}
	}

}
