<?Php

/**
 * \class User
 * \brief Handles the user's login and user.
 *
 */
class User {
	private $xmppSession;

	private $username = '';
	private $password = '';

	/**
	 * Class constructor. Reloads the user's session or attempts to authenticate
	 * the user.
	 * Note that the constructor is private. This class is a singleton.
	 */
	function __construct()
	{
        movim_log("1.");
		if($this->isLogged()) {
            $sess = Session::start(APP_NAME);
			$this->username = $sess->get('login');
			$this->password = $sess->get('pass');

			$this->xmppSession = Jabber::getInstance($this->username);
		}
		else if(isset($_POST['login'])
				&& isset($_POST['pass'])
				&& $_POST['login'] != ''
				&& $_POST['pass'] != '') {
			$this->authenticate($_POST['login'], $_POST['pass'], $_POST['host'], $_POST['suffix'], $_POST['port']);
		}
	}

	/**
	 * Checks if the user has an open session.
	 */
	function isLogged()
	{
		// User is not logged in if both the session vars and the members are unset.
        $sess = Session::start(APP_NAME);
		return (($this->username != '' && $this->password != '') || $sess->get('login'));
	}

	function authenticate($login,$pass, $boshhost, $boshsuffix, $boshport)
	{
		try{

            $data = false;
            if( !($data = $this->getConf($login)) ) {
			    // We check if we wants to create an account
                header('Location:'.BASE_URI.'index.php?q=disconnect&err=noaccount');
            }

			$this->xmppSession = Jabber::getInstance($login);
			$this->xmppSession->login($login, $pass);

			// Careful guys, md5 is _not_ secure. SHA1 recommended here.
			if(sha1($pass) == $data['pass']) {
                $sess = Session::start(APP_NAME);
                $sess->set('login', $login);
                $sess->set('pass', $pass);

				$this->username = $login;
				$this->password = $pass;
			} else {
				throw new MovimException(t("Wrong password"));
			}
		}
		catch(MovimException $e){
			echo $e->getMessage();
			return $e->getMessage();
		}
	}

	function desauth()
	{
        PresenceHandler::clearPresence();

        $sess = Session::start('jaxl');
        Session::dispose('jaxl');

        $sess = Session::start(APP_NAME);
        Session::dispose(APP_NAME);
	}

    function setLang($language)
    {
        global $sdb;
        $conf = $sdb->select('ConfVar', array('login' => $this->username));
        $conf[0]->language = $language;
        $sdb->save($conf[0]);
    }

	function setConf($data)
    {
        global $sdb;
        $conf = $sdb->select('ConfVar', array('login' => $this->username));
        $conf[0]->setConf(
                            $data['login'],
                            $data['pass'],
                            $data['host'],
                            $data['domain'],
                            $data['port'],
                            $data['boshhost'],
                            $data['boshsuffix'],
                            $data['boshport'],
                            $data['language'],
                            $data['first']
                         );
        $sdb->save($conf[0]);
	}

    function getConf($user = false, $element = false) {
        $login = ($user != false) ? $user : $this->username;

        global $sdb;
        $conf = $sdb->select('ConfVar', array('login' => $login));

        if($conf != false) {
            $array = $conf[0]->getConf();
            if($element != false)
	            return $array[$element];
	        else
	            return $array;
        } else {
            return false;
        }
    }

	function getLogin()
	{
		return $this->username;
	}

	function getPass()
	{
		return $this->password;
	}

}

