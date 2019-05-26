<?php
/**
 * SDK for AFIP Electronic Billing (wsfe1)
 *
 * @link http://www.afip.gob.ar/fe/documentos/manual_desarrollador_COMPG_v2_10.pdf WS Specification
 *
 * @author    Ivan Muñoz
 * @package Afip
 * @version 0.7
 **/

class ElectronicBillingWithItems extends AfipWebService {

    var $soap_version = SOAP_1_2;
    var $WSDL = 'wsmtxca-production.wsdl';
    var $URL = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx';
    var $WSDL_TEST = 'wsmtxca.wsdl';
    var $URL_TEST = 'https://fwshomo.afip.gov.ar/wsmtxca/services/MTXCAService';

    /**
     * Gets last voucher number
     *
     * Asks to Afip servers for number of the last voucher created for
     * certain sales point and voucher type {@see WS Specification
     * item 4.15}
     *
     * @param int $sales_point Sales point to ask for last voucher
     * @param int $type Voucher type to ask for last voucher
     *
     * @return int
     * @throws Exception
     * @since 0.7
     *
     */
    public function GetLastVoucher($sales_point, $type) {
        $params['consultaUltimoComprobanteAutorizadoRequest'] = array(
            'codigoTipoComprobante' => $type,
            'numeroPuntoVenta' => $sales_point
        );

        try {
            $result = $this->ExecuteRequest('consultarUltimoComprobanteAutorizado', $params);
        } catch (Exception $e) {
            if ($e->getCode() == 602)
                return NULL;
            else
                throw $e;
        }

        return isset($result->numeroComprobante) ? $result->numeroComprobante : false;
    }

    /**
     * Create a voucher from AFIP
     *
     * Send to AFIP servers request for create a voucher and assign
     * CAE to them {@see WS Specification item 4.1}
     *
     * @param array $data Voucher parameters {@see WS Specification
     *    item 4.1.3}, some arrays were simplified for easy use {@example
     *    examples/CreateVoucher.php Example with all allowed
     *     attributes}
     * @param bool $return_response if is TRUE returns complete response
     *    from AFIP
     *
     * @return array if $return_response is set to FALSE returns
     *    [CAE => CAE assigned to voucher, CAEFchVto => Expiration date
     *    for CAE (yyyy-mm-dd)] else returns complete response from
     *    AFIP {@see WS Specification item 4.1.3}
     * @throws SoapFault
     * @since 0.7
     *
     */
    public function CreateVoucher($data, $return_response = FALSE) {
        $total = $data['importeTotal'];
        $imp = $total - round($total / 1.21 , 2);
        $neto = $total - $imp;
        $req['comprobanteCAERequest'] = array(
            'codigoTipoComprobante' => $data['codigoTipoComprobante'],
            'numeroPuntoVenta' => $data['numeroPuntoVenta'],
            'numeroComprobante' => $data['numeroComprobante'],
            'fechaEmision' => date("Y-m-d", time()),
            'codigoTipoDocumento' => "96", //DNI: 96
            'numeroDocumento' => $data['numeroDocumento'],
            'importeGravado' => $neto, //importe neto
            'importeNoGravado' => 0,
            'importeExento' => 0,
            'importeSubtotal' => $data['importeGravado'],
            'importeTotal' => $data['importeTotal'],
            'codigoMoneda' => 'PES',
            'cotizacionMoneda' => 1,
            'codigoConcepto' => 1,
            'arraySubtotalesIVA' => array(
                "subtotalIVA" => array(
                    "codigo" => 5,
                    "importe" => $imp
                )
            ),
            'arrayItems' => $data["items"]
        );

        $results = $this->ExecuteRequest('autorizarComprobante', $req);

        if ($return_response === TRUE) {
            return $results;
        } else {
            return array(
                'CAE' => $results->comprobanteResponse->CAE,
                'CAEFchVto' => $results->comprobanteResponse->fechaVencimientoCAE,
            );
        }
    }

    /**
     * Create next voucher from AFIP
     *
     * This method combines Afip::GetLastVoucher and Afip::CreateVoucher
     * for create the next voucher
     *
     * @param array $data Same to $data in Afip::CreateVoucher except that
     *    don't need CbteDesde and CbteHasta attributes
     *
     * @return array [CAE => CAE assigned to voucher, CAEFchVto => Expiration
     *    date for CAE (yyyy-mm-dd), voucher_number => Number assigned to
     *    voucher]
     **@since 0.7
     *
     */
    public function CreateNextVoucher($data) {
        $last_voucher = $this->GetLastVoucher($data['numeroPuntoVenta'], $data['codigoTipoComprobante']);

        $voucher_number = $last_voucher + 1;
        $data['numeroComprobante'] = $voucher_number;

        $res = $this->CreateVoucher($data);

        return $res;
    }

    /**
     * Get complete voucher information
     *
     * Asks to AFIP servers for complete information of voucher {@see WS
     * Specification item 4.19}
     *
     * @param int $number Number of voucher to get information
     * @param int $sales_point Sales point of voucher to get information
     * @param int $type Type of voucher to get information
     *
     * @return array|null returns array with complete voucher information
     *    {@see WS Specification item 4.19} or null if there not exists
     **@throws Exception
     * @since 0.7
     */
    public function GetVoucherInfo($number, $sales_point, $type) {
        $req['consultaComprobanteRequest'] = array('codigoTipoComprobante' => $type,
            'numeroPuntoVenta' => $sales_point,
            'numeroComprobante' => $number
        );

        try {
            $result = $this->ExecuteRequest('consultarComprobante', $req);
        } catch (Exception $e) {
            if ($e->getCode() == 602)
                return NULL;
            else
                throw $e;
        }

        return $result;
    }

    /**
     * Asks to AFIP Servers for voucher types availables {@see WS
     * Specification item 4.4}
     *
     * @return array All voucher types availables
     **@since 0.7
     *
     */
    public function GetVoucherTypes() {
        return $this->ExecuteRequest('FEParamGetTiposCbte')->ResultGet->CbteTipo;
    }

    /**
     * Asks to AFIP Servers for voucher concepts availables {@see WS
     * Specification item 4.5}
     *
     * @return array All voucher concepts availables
     **@since 0.7
     *
     */
    public function GetConceptTypes() {
        return $this->ExecuteRequest('FEParamGetTiposConcepto')->ResultGet->ConceptoTipo;
    }

    /**
     * Asks to AFIP Servers for document types availables {@see WS
     * Specification item 4.6}
     *
     * @return array All document types availables
     **@since 0.7
     *
     */
    public function GetDocumentTypes() {
        return $this->ExecuteRequest('consultarTiposDocumento')->arrayTiposDocumento;
    }

    /**
     * Asks to AFIP Servers for aliquot availables {@see WS
     * Specification item 4.7}
     *
     * @return array All aliquot availables
     **@since 0.7
     *
     */
    public function GetAliquotTypes() {
        return $this->ExecuteRequest('FEParamGetTiposIva')->ResultGet->IvaTipo;
    }

    /**
     * Asks to AFIP Servers for currencies availables {@see WS
     * Specification item 4.8}
     *
     * @return array All currencies availables
     **@since 0.7
     *
     */
    public function GetCurrenciesTypes() {
        return $this->ExecuteRequest('FEParamGetTiposMonedas')->ResultGet->Moneda;
    }

    /**
     * Asks to AFIP Servers for voucher optional data available {@see WS
     * Specification item 4.9}
     *
     * @return array All voucher optional data available
     **@since 0.7
     *
     */
    public function GetOptionsTypes() {
        return $this->ExecuteRequest('FEParamGetTiposOpcional')->ResultGet->OpcionalTipo;
    }

    /**
     * Asks to AFIP Servers for tax availables {@see WS
     * Specification item 4.10}
     *
     * @return array All tax availables
     **@since 0.7
     *
     */
    public function GetTaxTypes() {
        return $this->ExecuteRequest('FEParamGetTiposTributos')->ResultGet->TributoTipo;
    }

    /**
     * Asks to web service for servers status {@see WS
     * Specification item 4.14}
     *
     * @return object { AppServer => Web Service status,
     * DbServer => Database status, AuthServer => Autentication
     * server status}
     **@since 0.7
     *
     */
    public function GetServerStatus() {
        return $this->ExecuteRequest('dummy');
    }

    /**
     * Change date from AFIP used format (yyyymmdd) to yyyy-mm-dd
     *
     * @param string|int date to format
     *
     * @return string date in format yyyy-mm-dd
     **@since 0.7
     *
     */
    public function FormatDate($date) {
        return date_format(DateTime::CreateFromFormat('Ymd', $date . ''), 'Y-m-d');
    }

    /**
     * Sends request to AFIP servers
     *
     * @param string $operation SOAP operation to do
     * @param array $params Parameters to send
     *
     * @param bool $wsmtxca
     * @return mixed Operation results
     * @throws SoapFault
     * @since 0.7
     *
     */
    public function ExecuteRequest($operation, $params = array()) {
        $params = array_replace($this->GetWSInitialRequest($operation), $params);

        $results = parent::ExecuteRequest($operation, $params);
        if (isset($results->arrayErrores)) {
            return false;
        }
        return $results;
    }

    /**
     * Make default request parameters for most of the operations
     *
     * @param string $operation SOAP Operation to do
     *
     * @return array Request parameters
     **@since 0.7
     *
     */
    private function GetWSInitialRequest($operation) {
        if ($operation == 'dummy') {
            return array();
        }

        $ta = $this->afip->GetServiceTA('wsmtxca');

        return array('authRequest' => array('token' => $ta->token,
            'sign' => $ta->sign,
            'cuitRepresentada' => $this->afip->CUIT));
    }

}

