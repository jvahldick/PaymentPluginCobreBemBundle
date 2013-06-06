<?php

namespace JHV\Payment\Plugin\CobreBemBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\Regex;

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
                'label'         => 'form_label.credit_card.holder',
                'required'      => false,
            ))
            ->add('number', 'text', array(
                'label'         => 'form_label.credit_card.number',
                'constraints'   => array(
                    new Callback(array('methods' => array(
                        array($this, 'isCreditCardValid')
                    ))),
                ),
                'required'      => false,
            ))
            ->add('expiration', 'date', array(
                'label'         => 'form_label.expiration',
                'format'        => 'ddMMyyyy',
                'days'          => array(1 => 1),
                'years'         => $choiceYears,
            ))
            ->add('code', 'text', array(
                'label'         => 'form_label.credit_card.ccv',
                'required'      => false,
                'max_length'    => 4,
                'constraints'   => new Regex(array(
                    'pattern'   => "/^[0-9]{3,4}$/", 
                    'message'   => "form.payment.credit_card.error.number.code"
                ))
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
        
        if (strlen($data['holder']) <= 3) {
            $form->get('holder')->addError(new FormError('form.payment.credit_card.error.min_length.holder'));
        }
        
        if (empty($data['number'])) {
            $form->get('number')->addError(new FormError('form.payment.credit_card.error.blank_error.number'));
        }
        
        if ($data['expiration'] <= new \DateTime('now')) {
            $form->get('expiration')->addError(new FormError('form.payment.credit_card.error.expiration_error.expiration'));
        }
        
        if (empty($data['code'])) {
            $form->get('code')->addError(new FormError('form.payment.credit_card.error.blank_error.code'));
        }
    }
    
    public function isCreditCardValid($value, ExecutionContext $context) 
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (false === is_numeric($value)) {
            $context->addViolation('form.payment.credit_card.error.numeric_error.number');
            return;
        }

        $length = strlen($value);
        $oddLength = $length % 2;
        for ($sum = 0, $i = $length - 1; $i >= 0; $i--) {
            $digit = (int) $value[$i];
            $sum += (($i % 2) === $oddLength) ? array_sum(str_split($digit * 2)) : $digit;
        }

        if ($sum === 0 || ($sum % 10) !== 0) {
            $context->addViolation('form.payment.credit_card.error.number_error.number');
        }
    }
    
}