<?php
/**
 * @author: Dimas
 * @mail: dimsaf@mail.ru
 * Date: 19.05.2020
 */

namespace LDAP_API\Entity;

use LDAP_API\LDAP\Ldap;
use LDAP_API\Receiver\Signature;

class User extends Entity {

	const AUTH_ORG = "OU=ldap";
	protected $getParams = [
		"login" => ["string", "(&(objectClass=person)(samaccountname=[[+value]]))"],
		"name"  => ["string", "(&(objectClass=person)(cn=[[+value]]*))"]
	];
	protected $fieldsRequired = ['fullname', 'login', 'phone', 'title'];

	/**
	 * Authentification with AD with|without token generation
	 * @return array|bool
	 */
	public function _auth() {
		try {
			// checking to the crypted pass
			if (strlen($this->inputData['pass']) > 10) $this->inputData['pass'] = Signature::decrypting($this->inputData['pass']);
			// authentification
			$ldap = new Ldap();
			$ldap->auth($this->inputData['login'], $this->inputData['pass']);

			// token generation if OU= special org only!
			$dn = $ldap->getDnByLogin($this->inputData['login'], true);
			if (empty($dn) || !is_array($dn)) return false;
			if (!preg_match("/" . self::AUTH_ORG . "/", $dn[0])) return false;
			$token = Signature::encrypting(time() . "=" . $_SERVER['REMOTE_ADDR']);

		} catch (\RuntimeException $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode());
		}

		return ['token' => $token];
	}

	/**
	 * Get users by search params matching
	 * @return mixed
	 */
	public function _search() {
		// prepare search string
		$param = array_key_first($this->inputData['search']);
		$value = $this->inputData['search'][$param];
		$param = $this->doReplacements($param);
		$searchString = "(&(objectClass=person)($param=*$value*))";
		$filters = isset($this->inputData['filters']) && !empty($this->inputData['filters']) ? $this->inputData['filters'] : ["*"];

		// getting data
		try {
			$ldap = new Ldap();
			// \Closure
			$result = $ldap->process(function () use ($ldap, $searchString, $filters) {
				return $ldap->getLdapData('', $searchString, $filters);
			});
		} catch (\RuntimeException $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode());
		}

		return $result;
	}

	public function _update() {
		// prepare required fields
		$this->inputData['fields'] = $this->doReplacements($this->inputData['fields']);

		try {
			$ldap = new Ldap();
			// \Closure
			$result = $ldap->process(function () use ($ldap) {
				$dn = $ldap->getDnByLogin($this->inputData['login']);
				if (empty($dn)) throw new \RuntimeException("User " . $this->inputData['login'] . " not found", 404);

				return $ldap->updateUser($dn[0], $this->inputData['fields']);
			});

			return $result;

		} catch (\RuntimeException $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode());
		}
	}

	public function _create() {
		// validation & prepare dn 		ex: OU=Test2,OU=Test,OU=ORG,
		$validate = false;
		$dn = '';
		$ldap = new Ldap();
		$org = array_shift($this->inputData['org']);
		foreach ($ldap->getLdaptree() as $tree) {
			$avilableOrg = explode(",", $tree)[0];
			if ("OU=" . $org == $avilableOrg) {
				$validate = true;
				$dn = "OU=" . implode(",OU=", array_reverse($this->inputData['org'])) . "," . $tree;
				break;
			}
		}
		if (!$validate) throw new \RuntimeException("Organization $org is not avilable to create new user", 403);

		foreach ($this->fieldsRequired as $param) {
			if (!isset($this->inputData['fields'][$param]) || empty($this->inputData['fields'][$param])) {
				throw new \RuntimeException ("Required property '" . $param . "' is missing or empty", 400);
			}
		}

		// parse FIO
		if (!empty($this->inputData['fields']['fullname'])) {
			$this->inputData['fields']['cn'] = $this->inputData['fields']['displayname'] = trim($this->inputData['fields']['fullname']);
			$fio = array_map("trim", explode(" ", $this->inputData['fields']['fullname']));
			$this->inputData['fields']['sn'] = $fio[0];
			if (isset($fio[1]) && !empty($fio[1])) $this->inputData['fields']['givenname'] = $fio[1];
			unset($this->inputData['fields']['fullname']);
		}

		// prepare required fields
		$this->inputData['fields'] = $this->doReplacements($this->inputData['fields']);
		$dn = "CN=" . $this->inputData['fields']['cn'] . "," . $dn;
		$this->inputData['fields']['objectclass'] = ['top', 'person', 'organizationalPerson', 'user'];
		$this->inputData['fields']['UserAccountControl'] = 32;        // требовать смену пароля при следующем входе

		try {
			// \Closure
			$result = $ldap->process(function () use ($ldap, $dn) {
				return $ldap->addUser($dn, $this->inputData['fields']);
			});
		} catch (\RuntimeException $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode());
		}
		if ($result == 1) $result = $this->inputData['fields']['samaccountname'];

		return $result;
	}

	public function _block() {
		// prepare search string
		$searchString = str_replace("[[+value]]", $this->inputData["login"], $this->getParams["login"][1]);
		$type = (isset($this->inputData["type"]) && $this->inputData["type"] == "unblock") ? -2 : 2;

		try {
			$ldap = new Ldap();
			// \Closure
			$result = $ldap->process(function () use ($ldap, $searchString, $type) {
				$data = $ldap->getLdapData('', $searchString, ["dn", "useraccountcontrol"]);
				if (empty($data)) throw new \RuntimeException("User " . $this->inputData['login'] . " not found", 404);

				$flag = $data[0]['useraccountcontrol'][0];
				$check = $ldap->checkUserDisabled($flag);
				if ($check == 'disabled' && $type == 2) throw new \RuntimeException("User is alredy blocked", 423);
				if ($check == 'enabled' && $type == -2) throw new \RuntimeException("User is alredy unblocked", 423);

				return $ldap->updateUser($data[0]['dn'], ['userAccountControl' => $flag + $type]);

			});

			return $result;

		} catch (\RuntimeException $e) {
			throw new \RuntimeException($e->getMessage(), $e->getCode());
		}
	}
}
