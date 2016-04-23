<?php
/**
 * Created by PhpStorm.
 * User: yanchunhao
 * Date: 2015/12/2
 * Time: 15:44
 */

namespace Swoole\Client;

use Swoole\CLPack;

class CLMySQL {
	private $conn, $dbname, $result = array(), $result_id = 1;
	private $host, $port;
	public $last_errno, $last_erro_msg, $is_connect = false;
	private static $conns = [];

	function __construct($host, $port, $dbname, $pconnect = true) {
		$this->host = $host;
		$this->port = $port;
		$this->dbname = $dbname;
		if (!isset(self::$conns[$host . ':' . $port])) {
			self::$conns[$host . ':' . $port] = new \swoole_client($pconnect ? (SWOOLE_SOCK_TCP | SWOOLE_KEEP) : SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC, 'clmysql');
			self::$conns[$host . ':' . $port]->set(array(
				'open_length_check' => 1,
				'package_length_type' => 'N',
				'package_length_offset' => 0,
				//第N个字节是包长度的值
				'package_body_offset' => 8,
				//第几个字节开始计算长度
				'package_max_length' => CLPack::MAX_LEN,
				//协议最大长度
			));
		}
		$this->conn = self::$conns[$host . ':' . $port];
		/*$this->conn->on('Close', array(
			$this,
			'OnClose'
		));*/
		$this->connect();
	}

	function select_db($dbname) {
		$this->dbname = $dbname;
		return true;
	}

	function connect($host = '', $port = 0) {
		if ($host) {
			$this->host = $host;
		}
		if ($port) {
			$this->port = $port;
		}
		$this->is_connect = $this->conn->connect($this->host, $this->port, 60);
		return $this->is_connect;
	}

	function getPack($sign) {
		while (1) {
			$data = @$this->conn->recv();
			if ($data == false) {
				#throw new \Exception('连接Mysql网络中断');
				$this->last_errno = 2006;
				$this->last_erro_msg = 'Mysql proxy中断(接收失败)';
				return false;
			}
			$r = CLPack::unpack($data);
			if ($r && $r[0] === $sign) {
				return $r[1];
			}
		}
	}

	function query($sql) {
		if (!$this->is_connect) {
			$this->last_errno = 2006;
			$this->last_erro_msg = 'Mysql proxy中断(无连接)';
			return false;
		}
		$is_multi = true;
		$sign = mt_rand();
		if (!is_array($sql)) {
			$is_multi = false;
			$sql = array($this->dbname => $sql);
		}
		$pack = CLPack::pack($sql, $sign);
		if (false === $pack) {
			$this->last_errno = 1256;
			$this->last_erro_msg = '发送的sql语句大小超过限制';
			return false;
		}
		if (false === $this->conn->send($pack)) {
			$this->conn->close();
			$this->connect();
			if (false === $this->conn->send($pack)) {
				$this->last_errno = 2006;
				$this->last_erro_msg = 'Mysql proxy中断(发送失败)';
				return false;
			}
		}
		$r = $this->getPack($sign);
		if (!is_array($r)) {
			return false;
		}
		foreach ($r as $k => $v) {
			if ($v[0] != 0) {
				$this->last_errno = $v[0];
				$this->last_erro_msg = $v[1];
				return false;
			}
		}

		$result_id = $this->result_id++;
		$this->result[$result_id] = $r;
		return $result_id;
	}

	function fetch($result_id, $dbname = '') {
		if (isset($this->result[$result_id])) {
			if (!$dbname) {
				$dbname = $this->dbname;
			}
			if ($this->result[$result_id][$this->dbname][0] == 0) {
				return $this->result[$result_id][$this->dbname][1];
			}
		}
		return false;
	}

	function fetch_row($result_id, $seek, $dbname = '') {
		if (isset($this->result[$result_id])) {
			if (!$dbname) {
				$dbname = $this->dbname;
			}
			if ($this->result[$result_id][$this->dbname][0] == 0 && isset($this->result[$result_id][$this->dbname][1][$seek])) {
				return $this->result[$result_id][$this->dbname][1][$seek];
			}
		}
		return false;
	}

	function num_rows($result_id, $dbname = '') {
		if (isset($this->result[$result_id])) {
			if (!$dbname) {
				$dbname = $this->dbname;
			}
			if ($this->result[$result_id][$this->dbname][0] == 0) {
				return count($this->result[$result_id][$this->dbname][1]);
			}
		}
		return 0;
	}

	function free_result($result_id) {
		if (isset($this->result[$result_id])) {
			unset($this->result[$result_id]);
		}
	}

	function insert_id() {
		$result_id = $this->query('insert_id');
		return $this->fetch($result_id);
	}

	function affected_rows() {
		$result_id = $this->query('affected_rows');
		return $this->fetch($result_id);
	}

	function onClose(\swoole_client $client) {
		//连接中断
		#throw new \Exception("clmysql连接被中断");
	}
}