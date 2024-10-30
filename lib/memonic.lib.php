<?php
/**
 * Memonic API class to handle all API calls
 */
class memonicAPI {
	private $API_endpoint;
	private $API_key;
	public $username;
	public $userpw;
	private $userId;
	private $userMeta;
	private $permissions;
	private $sets;
	private $groups;
	private $items;
	private $item;
	private $guestpass;
	private $errorMsg;
	
	public function __construct($user, $pw) {
		$this->API_endpoint = 'https://api.memonic.com/v2/';
		$this->API_key = '30939e59ce188340819bdaa7c64f9e42';
		
		$this->username = $user;
		$this->userpw = $pw;
	}
	
	/*
	 * magic methods
	 */
	public function __set($key, $val) {
	    $this->$key = $val;
	}

	public function __get($key) {
	    return $this->$key;
	}

    public function __isset($key) {
        return isset($this->$key);
    }

    public function __unset($key) {
		unset($this->$key);
    }
	 	/*
	 * helper method to do the actual HTTP request
	 */
	private function api_get_request($obj, $param=NULL) {
		$curUser = isset($param['uid']) ? $param['uid'] : $this->userId;

		/* create URI for REST call */
		$uri = $this->API_endpoint;
		$request_type = 'GET';
		
		switch ($obj) {
			case 'users':
				$uri .= 'users';
				break;
			case 'user':
				$uri .= 'users/'.$curUser;
				break;
			case 'permissions':
				$uri .= 'users/'.$curUser.'/permissions';
				break;
			case 'sets':
				$uri .= 'users/'.$curUser.'/sets';
				break;
			case 'set':
				$uri .= 'users/'.$curUser.'/sets/'.$param['sid'];
				break;
			case 'groups':
				$uri .= 'users/'.$curUser.'/groups';
				break;
			case 'group':
				$uri .= 'users/'.$curUser.'/groups/'.$param['gid'];
				break;
			case 'set_items':
				$uri .= 'users/'.$curUser.'/sets/'.$param['sid'].'/items';
				break;
			case 'group_items':
				$uri .= 'users/'.$curUser.'/groups/'.$param['sid'].'/items';
				break;
			case 'items':
				$uri .= 'users/'.$curUser.'/items';
				break;
			case 'item':
				$uri .= 'users/'.$curUser.'/items/'.$param['nid'];
				break;
			case 'guestpass':
				$uri .= 'users/'.$curUser.'/items/'.$param['nid'].'/guestpass';
				$request_type = 'POST';
				break;
		}

		$uri .= '.json?apikey='.$this->API_key;
		
		/* add page number and size if set */
		if (in_array($obj, array('items', 'set_items', 'group_items'))) {
			$uri .= isset($param['page']) ? '&page='.$param['page'] : '';
			$uri .= isset($param['pagesize']) ? '&pagesize='.$param['pagesize'] : '';
			$uri .= isset($param['view']) ? '&view='.$param['view'] : '';
		}
		
		/* set header arguments for GET request */
		$args = array(
			'timeout' => 5,
			'redirection' => 2,
			'headers' => array('Authorization' => 'Basic ' . base64_encode($this->username.':'.$this->userpw)),
		);

		/* grab URL and assign to variable */
		if ($request_type == 'GET')
			$http_result = wp_remote_get($uri, $args);
		if ($request_type == 'POST')
			$http_result = wp_remote_post($uri, $args);

		if (is_wp_error($http_result)) {
			/* if ($http_result === false) $this->errorMsg = __('Connection failed', 'memonic');
			else {
				$errMsg = json_decode($http_result);
				$this->errorMsg = $errMsg->message;
			} */
			$this->errorMsg = __('Connection failed', 'memonic');
			return false;
		} else {
			if ($http_result['response']['code'] >= 400) {
				$this->errorMsg = __('Authentication failed.', 'memonic');
				return false;
			} else {
				return json_decode($http_result['body']);
			}
		}
	}
	
	/**
	 * get current user object
	 */
	public function getUser() {
		if ($data = $this->api_get_request('users')) {
			$this->userId = $data->users[0]->id;
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * get user meta data
	 */
	
	public function getUserDetail($uid=NULL) {
		if ($data = $this->api_get_request('user', array('uid' => $uid))) {
			$this->userMeta = $data;
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * get all notes for a user
	 */
	public function getNotes($p=NULL, $ps=NULL, $view='minimal', $uid=NULL) {
		if ($data = $this->api_get_request('items', array('uid' => $uid, 'page' => $p, 'pagesize' => $ps, 'view' => $view))) {
			$this->items=$data;			
		} else {
			echo $this->errorMsg;
		}
	}

	/**
	 * get a specific item
	 */
	public function getNote($nid, $uid=NULL) {
		if ($data = $this->api_get_request('item', array('uid' => $uid, 'nid' => $nid))) {
			$this->item = $data;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * get a guestpass for a specific note
	 */
	public function getGuestpass($nid, $uid=NULL) {
		if ($data = $this->api_get_request('guestpass', array('uid' => $uid, 'nid' => $nid))) {
			$this->guestpass = $data;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * get a list of folders for a user
	 */
	public function getFolders($uid=NULL) {
		if ($data = $this->api_get_request('sets', array('uid' => $uid))) {
			$this->sets = $data;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * get meta data about one folder for a user
	 */
	public function getFolder($sid, $uid=NULL) {
		if ($data = $this->api_get_request('set', array('uid' => $uid, 'sid' => $sid))) {
			$this->sets = $data;
			return true;			
		} else {
			return false;
		}
	}

	/*
	 * get items of folder for a user
	 */
	public function getFolderItems($sid, $p=NULL, $ps=NULL, $view='minimal', $uid=NULL) {
		if ($data = $this->api_get_request('set_items', array('uid' => $uid, 'sid' => $sid, 'page' => $p, 'pagesize' => $ps, 'view' => $view))) {
			$this->items = $data;
			return true;			
		} else {
			return false;
		}
	}
	
	/*
	 * get a list of folders for a user
	 */
	public function getGroups($uid=NULL) {
		if ($data = $this->api_get_request('groups', array('uid' => $uid))) {
			$this->groups = $data;
			return true;			
		} else {
			return false;
		}
	}

	/*
	 * get meta data about one folder for a user
	 */
	public function getGroup($gid, $uid=NULL) {
		if ($data = $this->api_get_request('group', array('uid' => $uid, 'gid' => $gid))) {
			$this->groups = $data;
			return true;			
		} else {
			return false;
		}
	}

	/*
	 * get items of group for a user
	 */
	public function getGroupItems($sid, $p=NULL, $ps=NULL, $view='minimal', $uid=NULL) {
		if ($data = $this->api_get_request('group_items', array('uid' => $uid, 'sid' => $sid, 'page' => $p, 'pagesize' => $ps, 'view' => $view))) {
			$this->items = $data;
			return true;			
		} else {
			return false;
		}
	}
	
	/*
	 * get permissions with label
	 */
	public function getPermissions($uid=NULL) {
		if ($data = $this->api_get_request('permissions', array('uid' => $uid))) {
			$this->permissions = $data;
			return true;			
		} else {
			return false;
		}
	}
	

}
