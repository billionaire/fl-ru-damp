<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/classes/YandexMoney3/YandexMoney3.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/classes/yandex_kassa.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/classes/billing/Exception/BillPaybackException.php');

use YandexMoney3\Request\ReturnPaymentRequest;
use YandexMoney3\YandexMoney3;

class BillPayback {
    
    protected static $instance;
    
    const PAYBACK_CAUSE     = '������� ���c������������ �������';
    
    const STATUS_NEW        = -1;
    const STATUS_SUCCESS    = 0;
    const STATUS_INPROGRESS = 1;
    const STATUS_FAIL       = 3;
    
    const CURRENCY_TEST     = 10643;
    const CURRENCY          = 643;
    
    private $currency;    
    
    private $isTest         = false;
    
    private $apiFacade      = null;

    private $TABLE          = 'bill_payback';
    
    
    public function __construct() 
    {
        $this->isTest = !is_release() || is_local();
    }

    
    public function getCurrency()
    {
        return $this->currency = ($this->isTest)?
                self::CURRENCY_TEST:
                self::CURRENCY;
    }


    public function setTest($value = true)
    {
        $this->isTest = $value;
    }


    
    public function getApiFacade()
    {
        if(!$this->apiFacade) 
        {
            $this->apiFacade = YandexMoney3::getMwsApiFacade(array(
                'crypt' => array(
                    'encrypt_cert_path' => ABS_PATH . '/classes/reserves/data_mws/certnew_vaan.cer',
                    'private_key_path'  => ABS_PATH . '/classes/reserves/data_mws/private_mws.key',
                    'passphrase'        => 'swirls53.quarks'
                ),
                'uri_test' => 'https://penelope-demo.yamoney.ru:8083',
                'uri_main' => 'https://penelope.yamoney.ru',
                'is_test'  => $this->isTest
            )); 
        }
        
        return $this->apiFacade;
    }

    
    
    
    /**
     * ������� ������ � ������� �� ������� �������
     * 
     * @param int $id
     * @return boolean
     * @throws BillPaybackException
     */
    public function doPayback($id)
    {
        $paybackData = $this->db()->row("
            SELECT *
            FROM {$this->TABLE}
            WHERE id = ?i
        ",$id);
        
        //���� ������� �� ����������    
        if(!$paybackData) 
            throw new BillPaybackException(BillPaybackException::PAYBACK_NOTFOUND);
        
        $is_timeout = $this->isTimeout($paybackData['cnt'], $paybackData['last']);
        //������� ��� �� ����� ����� ��������� � �������
        if(!$is_timeout) return false;
        
        //�������� ����� ��������� ���� ��� ����� �������
        if($is_timeout === -1) 
            throw new BillPaybackException(BillPaybackException::REQUEST_LIMIT);
        
        $data['cnt'] = $paybackData['cnt'] + 1;
        $data['last'] = 'NOW()';
        
        try
        {
            //������� ������
            $returnPaymentRequest = new ReturnPaymentRequest();
            $returnPaymentRequest->setShopId(yandex_kassa::SHOPID_DEPOSIT);
            $returnPaymentRequest->setClientOrderId($paybackData['id']);
            $returnPaymentRequest->setInvoiceId($paybackData['invoice_id']);
            $returnPaymentRequest->setCurrency($this->getCurrency());
            $returnPaymentRequest->setCause(self::PAYBACK_CAUSE);
            $returnPaymentRequest->setAmount(number_format($paybackData['price'], 2, '.', ''));
            //������ ������ � API �������
            $result = $this->getApiFacade()->returnPayment($returnPaymentRequest);
            
            //���������� ������ � ��� ������
            $data['status'] = $result->getStatus();
            $data['error'] = (!$result->isSuccess())?$result->getError():0;
        }
        catch(Exception $e)
        {
            //� ������ ������ ��� ���������� API 
            //����� � ��� � ������ ��������� ������ � �������
            $data['status'] = self::STATUS_FAIL;
            $data['error'] = 10000 + intval($e->getCode());
            $this->db()->update($this->TABLE, $data,'id = ?i', $paybackData['id']);
            throw new BillPaybackException($e->getMessage(),true);
        }
        
        $this->db()->update($this->TABLE, $data, 'id = ?i', $result->getClientOrderId());
        
        
        //������ ��� ������� ������� � ������� ��� ������
        if(!$result->isSuccess() && in_array($result->getError(), array(403, 404, 405, 412, 413, 414, 417)))
            throw new BillPaybackException(BillPaybackException::API_CRITICAL_FAIL, $result->getError()); 
        
        
        //���� ������ ��� �� ������� �� ����� 
        //��������� � ��������� ������ � �������
        return $data['error'] == 0;
    }


    /**
     * ������ �� ������� �������
     * ��� �������� �� ����������� ������������ ������
     * ��� �� ������ � �������
     * 
     * @param int $src_id
     * @param int $invoice_id
     * @param float $sum
     * @throws BillPaybackException
     */
    public function requestPayback($src_id, $invoice_id, $sum)
    {
        $paybackData = $this->db()->row("
            SELECT *
            FROM {$this->TABLE}
            WHERE src_id = ?i
        ", $src_id);
        
        $id = null;
           
        if($paybackData)
        {
            if($paybackData['status'] == self::STATUS_SUCCESS)
                throw new BillPaybackException(BillPaybackException::ALREADY_PAYBACK_MSG, $paybackData['id']);
            
            if($paybackData['status'] == self::STATUS_INPROGRESS)
                throw new BillPaybackException(BillPaybackException::PAYBACK_INPROGRESS,  $paybackData['id']);

            $is_ok = $this->db()->update($this->TABLE, array(
                'invoice_id' => $invoice_id,
                'price' => $sum,
            ), 'id = ?i', $paybackData['id']);
            
            if($is_ok) $id = $paybackData['id'];
        }
        else
        {
            $id = $this->db()->insert($this->TABLE, array(
                'src_id' => $src_id,
                'invoice_id' => $invoice_id,
                'price' => $sum
            ),'id');
        }
            
        if(!$id) throw new BillPaybackException(BillPaybackException::INSERT_FAIL_MSG);
        
        //��������� � ������� �� ���������
        $this->db()->query("SELECT pgq.insert_event('payback', 'payback', ?)", 
                http_build_query(array('id' => $id)));
    }

    

    /**
     * ������������� ��������� ����� �������: ������ ������ ����� 1 ������, 
     * ��������� ��� � ����������� � 5 �����, ����� �� ���� ��� ��� � 30 �����.
     */
    private function isTimeout($cnt, $timeString)
    {
        if($cnt == 0) return true;
        if($cnt >= 999) return -1;
        
        $timeout = 1800;
        if($cnt == 1) $timeout = 60;
        elseif($cnt >= 2 && $cnt <= 4) $timeout = 300;
        
        $time = strtotime($timeString) + $timeout;
        return (time() < $time)?false:true;
    }


    /**
     * ������� ��������
     * @return object
     */
    public static function getInstance() 
    {

        if (null === static::$instance) {
            static::$instance = new static;
        }

        return static::$instance;
    }
    
    /**
     * @return DB
     */
    public function db()
    {
        return $GLOBALS['DB'];
    }
    
}
