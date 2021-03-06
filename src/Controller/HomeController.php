<?php

namespace Opportus\Blog;

use Opportus\Session\Session;
use Psr\Http\Message\ResponseInterface;
use PHPMailer\PHPMailer\PHPMailer;
use \Exception;

/**
 * The homepage controller...
 *
 * @version 0.0.1
 * @package Opportus\Blog
 * @author  Clément Cazaud <opportus@gmail.com>
 */
class HomeController extends AbstractController
{
	/**
	 * @var Session $session
	 */
	protected $session;

	/**
	 * @var ResponseInterface $response
	 */
	protected $response;

	/**
	 * @var PHPMailer $mailer
	 */
	protected $mailer;

	/**
	 * Constructor.
	 *
	 * @param Session           $session
	 * @param ResponseInterface $response
	 * @param PHPMailer         $mailer
	 */
	public function __construct(Session $session, ResponseInterface $response, PHPMailer $mailer)
	{
		$this->session  = $session;
		$this->response = $response;
		$this->mailer   = $mailer;
	}

	/**
	 * Renders the view.
	 */
	public function view()
	{
		$sessionToken = $this->session->set('contactFormToken', hash_hmac('sha256', bin2hex(random_bytes(32)), APP_SECRET_KEY));

		$body = $this->response->getBody();

		$body->write($this->render(TEMPLATE_DIR . '/home.php', array(
			'token' => hash_hmac('sha256', 'contactFormToken', $sessionToken)
		)));

		$this->response->withBody($body)->send();
	}

	/**
	 * Contacts.
	 */
	public function contact()
	{
		if (! isset($_POST['token']) || ! hash_equals($_POST['token'], hash_hmac('sha256', 'contactFormToken', $this->session->get('contactFormToken')))) {
			$this->session->destroy();

			throw new Exception('[' . __CLASS__ . '::' . __FUNCTION__ . ']: Invalid token for IP: ' . $_SERVER['REMOTE_ADDR']);
		}

		$email   = $_POST['email'];
		$name    = $_POST['name'];
		$message = $_POST['message'];
		$errors  = array();

		if (empty($email)) {
			$errors['email'] = 'Required email';

		} elseif (! preg_match('/^[a-z0-9_.-]{4,50}@[a-z0-9_.-]{2,}\.[a-z]{2,4}$/', $email)) {
			$errors['email'] = 'Invalid email';
		}

		if (empty($name)) {
			$errors['name'] = 'Required name';

		} elseif (! preg_match('/^[\p{L}\s]{0,50}$/u', $name)) {
			$errors['name'] = 'Invalid name';
		}

		if (empty($message)) {
			$errors['message'] = 'Required message';

		} else {
			$message = substr(filter_var($message, FILTER_SANITIZE_FULL_SPECIAL_CHARS), 0, 16384);
		}

		try {
			if (empty($errors)) {
				$this->mailer->isSMTP();
				$this->mailer->SMTPDebug  = APP_DEBUG;
				$this->mailer->SMTPAuth   = APP_SMTP_AUTH;
				$this->mailer->SMTPSecure = APP_SMTP_SECURE;
				$this->mailer->Host       = APP_SMTP_HOST;
				$this->mailer->Port       = APP_SMTP_PORT;
				$this->mailer->Username   = APP_SMTP_USERNAME;
				$this->mailer->Password   = APP_SMTP_PASSWORD;

				$this->mailer->setFrom(APP_SMTP_USERNAME, APP_NAME . ' Mailer');
				$this->mailer->addAddress(APP_EMAIL_ADDRESS);
				$this->mailer->addReplyTo($email);

				$this->mailer->Subject = 'Message from a user of ' . APP_NAME;
				$this->mailer->Body    = $message . "\n\n" . $name;

				if (! $this->mailer->send()) {
					throw new Exception($this->mailer->errorInfo);
				}

				$notif = 'I\'ll read your message soon, thanks !';

			} else {
				$notif = implode(' - ', $errors) . '...';
			}

		} catch (Exception $e) {
			$notif = 'Your message has not been sent. Please try again.';

		} finally {
			$ajaxResponse = json_encode(array(
				'errors'    => empty($errors) ? false : $errors,
				'notif'     => $notif,
				'redirect'  => false,
				'resetForm' => false
			));

			$body = $this->response->getBody();
			$body->write($ajaxResponse);
			$this->response->withHeader('Content-Type', 'application/json')->withBody($body)->send();

			exit;
		}
	}
}

