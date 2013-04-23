<?php

namespace JHV\Payment\Plugin\CobreBemBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;

use Symfony\Component\Form\FormError;
use JHV\Payment\ServiceBundle\Manager\PaymentMethodManagerInterface;


/**
 * CreditCardType
 * 
 * @author Jorge Vahldick <jvahldick@gmail.com>
 * @license Please view /Resources/meta/LICENCE
 * @copyright (c) 2013
 */
class CreditCardType extends AbstractType
{
    
    protected $paymentMethodManager;
    
    public function __construct(PaymentMethodManagerInterface $paymentMethodManager)
    {
        $this->paymentMethodManager = $paymentMethodManager;
    }
    
    public function buildForm(\Symfony\Component\Form\FormBuilderInterface $builder, array $options)
    {
        $self = $this;
        
        // Intervalo de anos
        $rangeYear = range(date('Y'), date('Y') + 10);
        $choiceYears = array_combine(array_values($rangeYear), $rangeYear);
        
        $builder
            ->add('holder', 'text', array(
                'label'     => 'form_label.credit_card.holder',
                'required'  => false,
            ))
            ->add('number', 'text', array(
                'label'     => 'form_label.credit_card.number',
                'required'  => false,
            ))
            ->add('expiration', 'date', array(
                'format'    => 'ddMMyyyy',
                'days'      => array(1 => 1),
                'years'     => $choiceYears,
            ))
            ->add('code', 'text', array(
                'label'         => 'form_label.credit_card.ccv',
                'required'      => false,
                'max_length'    => 3,
            ))
            ->addEventListener(FormEvents::POST_BIND, function (FormEvent $event) use ($self) {
                $form = $event->getForm();
                if ($form->hasParent()) {
                    $payment_method = $form->getParent()->get('payment_method')->getData();
                    if (null !== $method = $self->getPaymentMethodManager()->get($payment_method)) {
                        $plugin = $method->getPlugin();
                        if ('data_' . $plugin->getName() === $form->getName()) {
                            $self->validate($form);
                        }
                    }
                }
            });
        ;
    }
    
    public function getName()
    {
        return 'cobre_bem_credit_card_type';
    }
    
    public function getPaymentMethodManager()
    {
        return $this->paymentMethodManager;
    }
    
    public function validate($form)
    {
        $data = $form->getData();
        
        if (empty($data['holder'])) {
            $form->get('holder')->addError(new FormError('form.payment.credit_card.error.not_blank.holder'));
        }
        
        if (strlen($data['holder']) <= 5) {
            $form->get('holder')->addError(new FormError('form.payment.credit_card.error.min_length.holder'));
        }
        
        if (empty($data['number'])) {
            $form->get('number')->addError(new FormError('form.payment.credit_card.error.blank_error.number'));
        }
        
        if (false === is_numeric($data['number'])) {
            $form->get('number')->addError(new FormError('form.payment.credit_card.error.numeric_error.number'));
        }
        
        if ($data['expiration'] <= new \DateTime('now')) {
            $form->get('expiration')->addError(new FormError('form.payment.credit_card.error.expiration_error.expiration'));
        }
        
        if (empty($data['code'])) {
            $form->get('code')->addError(new FormError('form.payment.credit_card.error.blank_error.code'));
        }
        
        if (strlen($data['code']) != 3) {
            $form->get('code')->addError(new FormError('form.payment.credit_card.error.error_length.code'));
        }
    }
    
}