<?php

require_once "class.smbhash.inc.php";

function smbpass($value) {
	$smbhash = new smbHash();
	return $smbhash->nthash($value);
}

function unixpass($password) {
	// SSHA with random 4-character salt
	// http://blog.michael.kuron-germany.de/2012/07/hashing-and-verifying-ldap-passwords-in-php/
	
	$salt = substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',4)),0,4);
	return '{SSHA}' . base64_encode(sha1( $password.$salt, TRUE ). $salt);
}
