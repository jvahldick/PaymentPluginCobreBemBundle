<?php

namespace JHV\Payment\Plugin\CobreBemBundle\Gateway;

use JHV\Payment\ServiceBundle\Plugin\GatewayPlugin;
use JHV\Payment\CoreBundle\Financial\TransactionInterface;
use JHV\Payment\ServiceBundle\Model\PaymentMethodInterface;
use JHV\Payment\ServiceBundle\Http\Request;

/**
 * CreditCardPlugin
 * 
 * @author Jorge Vahldick <jvahldick@gmail.com>
 * @license Please view /Resources/meta/LICENCE
 * @copyright (c) 2013
 */
class CreditCardPlugin extends GatewayPlugin
{
    
    protected $enderecoAutorizacao;
    protected $enderecoCaptura;
    protected $enderecoCancelamento;
    
    public function __construct($destinoAutorizacao, $destinoCaptura, $destinoCancelamento)
    {
        $this->enderecoCancelamento = $destinoCancelamento;
        $this->enderecoAutorizacao = $destinoAutorizacao;
        $this->enderecoCaptura = $destinoCaptura;

        parent::__construct();
    }
    
    public function authorizeCapture(TransactionInterface $transaction, PaymentMethodInterface $method)
    {        
        // Efetuar primeiramente a autorização, pois no caso da cobre bem
        // não há captura automática já na solicitação de autorização
        $this->authorize($transaction, $method);
        
        // Caso tenha ID de transação, poderá efetuar a captura
        if ($transaction->getTransactionId()) {
            $this->capture($transaction, $method);
        }
    }
    
    public function authorize(TransactionInterface $transaction, PaymentMethodInterface $method)
    {
        $instruction = $transaction->getOperation()->getInstruction();
        $data = $instruction->getExtendedData();
        $extra = $method->getExtendedData();
              
        // Definição dos parâmetros para solicitação da autorização
        /** TODO: buscar o número do pedido, verificar parcelas */
        // Adicionar no array:
        // 'NumeroDocumento'       => 'numero_pedido',
        $parameters = array(
            
            'ValorDocumento'        => $transaction->getRequestedAmount(),
            'NomePortadorCartao'    => $data['holder'],
            'QuantidadeParcelas'    => '01',
            'NumeroCartao'          => $data['number'],
            'MesValidade'           => $data['expiration']->format('m'),
            'AnoValidade'           => $data['expiration']->format('y'),
            'CodigoSeguranca'       => $data['code'],
            'EnderecoIPComprador'   => $_SERVER['REMOTE_ADDR'],
            'Bandeira'              => $method->getCode(),
            'Adquirente'            => $extra['operadora'],
            'ResponderEmUTF8'       => 'S'
        );
        
        $request    = new Request($this->enderecoAutorizacao, 'POST', $parameters);
        $response   = $this->bind($request);
        
        $extendedData = array('autorizacao' => $this->xmlToArrayConversion(simplexml_load_string($response->getContent())));
        $transaction->setReturnedData($extendedData);
        if (isset($extendedData['autorizacao']['TransacaoAprovada']) && 'True' === $extendedData['autorizacao']['TransacaoAprovada']) {
            $transaction->setProcessedAmount($transaction->getRequestedAmount());
            $transaction->setTransactionId($extendedData['autorizacao']['Transacao']);
        } else {
            $transaction->setStatus(TransactionInterface::STATUS_CANCELED);
        }
    }
    
    public function capture(TransactionInterface $transaction, PaymentMethodInterface $method)
    {        
        if (null === $transaction->getTransactionId()) {
            throw new \InvalidArgumentException('A transação para ser capturada obrigatoriamente deve possuir um ID de transação já registrado');
        }
        
        $parameters     = array('Transacao' => $transaction->getTransactionId());
        $request        = new Request($this->enderecoCaptura, 'POST', $parameters);
        $response       = $this->bind($request);
        $returnedData   = array_merge($transaction->getReturnedData(), array('captura' => $this->xmlToArrayConversion(simplexml_load_string($response->getContent()))));
        $transaction->setReturnedData($returnedData);
        
        // Verifica se houve o resultado esperado, gerando erro em caso de não haver
        if (false === isset($returnedData['captura']['ResultadoSolicitacaoConfirmacao'])) {
            throw new \RuntimeException('Não foi possível verificar o retorno de captura junto a operadora');
        }
        
        // Verificação de erro, caso tenha entrado no IF é porque ocorreu erro
        if (false !== strpos($returnedData['captura']['ResultadoSolicitacaoConfirmacao'], 'Erro')) {
            $transaction->setProcessedAmount(0.00);
            $transaction->setStatus(TransactionInterface::STATUS_CANCELED);
        } else {
            $transaction->setProcessedAmount($transaction->getRequestedAmount());
            $transaction->setStatus(TransactionInterface::STATUS_SUCCESS);
        }
    }
    
    public function refund(TransactionInterface $transaction, PaymentMethodInterface $method)
    {
        if (null === $transaction->getTransactionId()) {
            throw new \InvalidArgumentException('Para transação ser cancelada obrigatoriamente deve possuir um ID de transação já registrado');
        }
        
        $parameters     = array('Transacao' => $transaction->getTransactionId());
        $request        = new Request($this->enderecoCancelamento, 'POST', $parameters);
        $response       = $this->bind($request);
        $returnedData   = array_merge($transaction->getReturnedData(), array('cancelamento' => $this->xmlToArrayConversion(simplexml_load_string($response->getContent()))));
        $transaction->setReturnedData($returnedData);
        
        /** @todo Verificar se os dados estão corretos para este modelo de operação */
        if (isset($returnedData['cancelamento']['ResultadoSolicitacaoCancelamento']) && false !== strpos($returnedData['cancelamento']['ResultadoSolicitacaoCancelamento'], 'Cancelado')) {
            $transaction->setProcessedAmount(0.0);
            $transaction->setStatus(TransactionInterface::STATUS_CANCELED);
        } else {
            
        }
    }
    
    /**
     * Conversão de XML para Array.
     * Método que utiliza-se de json para conversão dos dados retornados
     * pela operadora de XML neste caso específico, para um array.
     * 
     * @param \SimpleXMLElement $element
     * @return array
     */
    protected function xmlToArrayConversion(\SimpleXMLElement $element)
    {
        return json_decode(json_encode((array) $element), 1);
    }
    
    public function getName()
    {
        return 'cobre_bem_credit_card';
    }
    
}