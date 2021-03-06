<?
/**
 * ���������� ���� ��� ������ � �������
 */
require_once($_SERVER['DOCUMENT_ROOT'] . "/classes/blogs_proto.php");
/**
 * ����� ��� ������ � ����������� ������
 *
 */
class blogs_payed extends blogs_proto
{
	/**
     * ������� ����� ���������
     *
     * @param string $error  ���������� ��������� �� ������
     * @return integer 0;
     */
	function GetThread(&$error){
		$curname = get_class($this);
		
		global $DB;
		$sql = "SELECT id, fromuser_id, reply_to, post_time, msgtext, attach, title, uname, usurname, login, photo, role, modified, modified_id, deluser_id, deleted, small, is_pro as payed
		FROM  $curname
		LEFT JOIN users ON fromuser_id=uid 
		ORDER BY reply_to, post_time";
		$res = $DB->squery( $sql );
		$error .= pg_errormessage();
		if ($error) $error = parse_db_error($error);
		 else {
		 	$this->thread = pg_fetch_all($res);
		 	$this->msg_num = pg_num_rows($res);
		 	if ($this->msg_num > 0) $this->SetVars($this->msg_num-1);
		 }
		return 0;
	}
	/**
	 * ��������� �������������� ���������� � ���������
	 *
	 * @param integer $msg_id  �� ���������
	 * @param string  $error   ���������� ��������� �� ������
	 * @return array  $ret     ���������� �������
	 */
	function GetMsgInfo($msg_id, &$error){
		$curname = get_class($this);
		
		global $DB;
        $sql = "SELECT * FROM $curname LEFT JOIN users ON users.uid=$curname.fromuser_id WHERE id = ?";
        $res = $DB->query( $sql, $msg_id );
        $error = pg_errormessage();
        if (!$error && pg_num_rows($res) > 0){
            $ret = pg_fetch_assoc($res);
           //���������� $kind
			$ret['kind'] = $kind;
        }
        return $ret;
     }
}
?>