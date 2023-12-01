<?php

namespace App\Http\Livewire;

use Cart;
use Paystack;
use App\Models\Order;
use App\Mail\OrderMail;
use Paystack\Customer;
use App\Models\Payment;
use Livewire\Component;
use App\Models\Shipping;
use App\Models\OrderItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;
use Stripe;
// use PhpParser\Node\Stmt\TryCatch;

class CheckoutComponent extends Component
{
    public $ship_to_different;

    public $firstname;
    public $lastname;
    public $email;
    public $mobile;
    public $line1;
    public $line2;
    public $city;
    public $province;
    public $country;
    public $zipcode;

    public $s_firstname;
    public $s_lastname;
    public $s_email;
    public $s_mobile;
    public $s_line1;
    public $s_line2;
    public $s_city;
    public $s_province;
    public $s_country;
    public $s_zipcode;

    public $paymentmode;
    public $thankyou;
    public $card_no;
    public $exp_month;
    public $exp_year;
    public $cvc;

    public $amount;
    public $qty;

    public $flag = 0;
    public function updated($fields)
    {
        $this->validateOnly($fields, [

            'firstname' => 'required',
            'lastname' => 'required',
            'email' => 'required|email',
            'mobile' => 'required|numeric',
            'line1' => 'required',
            'city' => 'required',
            'province' => 'required',
            'country' => 'required',
            'zipcode' => 'required',
            'paymentmode' => 'required'
        ]);

        if ($this->ship_to_different) {
            $this->validateOnly($fields, [
                's_firstname' => 'required',
                's_lastname' => 'required',
                's_email' => 'required|email',
                's_mobile' => 'required|numeric',
                's_line1' => 'required',
                's_city' => 'required',
                's_province' => 'required',
                's_country' => 'required',
                's_zipcode' => 'required'
            ]);
        }
        if ($this->paymentmode == 'card') {
            $this->validateOnly($fields, [

                'card_no'=>'required|numeric',
                'exp_month'=>'required|numeric',
                'exp_year'=>'required|numeric',
                'cvc'=>'required|numeric'
            ]);
        }
    }


    public function placeOrder()
    {
        $this->validate([

            'firstname' => 'required',
            'lastname' => 'required',
            'email' => 'required|email',
            'mobile' => 'required|numeric',
            'line1' => 'required',
            'city' => 'required',
            'province' => 'required',
            'country' => 'required',
            'zipcode' => 'required',
            'paymentmode' => 'required'
        ]);

        if ($this->paymentmode == 'card') {
            $this->validate([
                'card_no'=>'required|numeric',
                'exp_month'=>'required|numeric',
                'exp_year'=>'required|numeric',
                'cvc'=>'required|numeric'


            ]);
        }
        $order = new Order();
        if(session()->has('coupon'))
        {
            $order->user_id = Auth::user()->id;
            $order->subtotal = session()->get('checkout')['subtotal'];
            $order->total = session()->get('checkout')['total'] ?? True;
            $order->firstname = $this->firstname;
            $order->lastname = $this->lastname;
            $order->email = $this->email;
            $order->mobile = $this->mobile;
            $order->line1 = $this->line1;
            $order->line2 = $this->line2;
            $order->city = $this->city;
            $order->province = $this->province;
            $order->country = $this->country;
            $order->zipcode = $this->zipcode;
            $order->status = 'ordered';
            $order->is_shipping_different = $this->ship_to_different ? 1 : 0;
            $order->save();

        }
        else
        {
            $order->user_id = Auth::user()->id;

            $order->subtotal = session()->get('checkout', Cart::instance('cart')->subtotal());
            $order->total = session()->get('checkout', Cart::instance('cart')->subtotal());
            $order->firstname = $this->firstname;
            $order->lastname = $this->lastname;
            $order->email = $this->email;
            $order->mobile = $this->mobile;
            $order->line1 = $this->line1;
            $order->line2 = $this->line2;
            $order->city = $this->city;
            $order->province = $this->province;
            $order->country = $this->country;
            $order->zipcode = $this->zipcode;
            $order->status = 'ordered';
            $order->is_shipping_different = $this->ship_to_different ? 1 : 0;
            $order->save();

        }

        foreach (Cart::instance('cart')->content() as $item) {
            $orderItem = new OrderItem();
            $orderItem->product_id = $item->id;
            $orderItem->order_id = $order->id;
            $orderItem->price = $item->price;
            $orderItem->quantity = $item->qty;
            $orderItem->save();
        }
        if ($this->ship_to_different) {
            $this->validate([
                's_firstname' => 'required',
                's_lastname' => 'required',
                's_email' => 'required|email',
                's_mobile' => 'required|numeric',
                's_line1' => 'required',
                's_city' => 'required',
                's_province' => 'required',
                's_country' => 'required',
                's_zipcode' => 'required'
            ]);

            $shipping = new Shipping();
            $shipping->order_id = $order->id;
            $shipping->firstname = $this->s_firstname;
            $shipping->lastname = $this->s_lastname;
            $shipping->email = $this->s_email;
            $shipping->mobile = $this->s_mobile;
            $shipping->line1 = $this->s_line1;
            $shipping->line2 = $this->s_line2;
            $shipping->city = $this->s_city;
            $shipping->province = $this->s_province;
            $shipping->country = $this->s_country;
            $shipping->zipcode = $this->s_zipcode;
            $shipping->save();
        }

        if ($this->paymentmode == 'cod') {
            $this->makeTransaction($order->id, 'pending');
            $this->resetCart();
        }
        else if ($this->paymentmode == 'card') {
            $stripe = Stripe::make(env('STRIPE_KEY'));

            try{
                $token = $stripe->tokens()->create([
                    'card'=>[
                        'number'=>$this->card_no,
                        'exp_month'=>$this->exp_month,
                        'exp_year'=>$this->exp_year,
                        'cvc'=> $this->cvc
                    ]

                ]);

                if(!isset($token['id']))
                {
                    session()->flash('stripe_error','The stripe token was not generated correctly!');
                    $this->thankyou = 0;
                }

                $customer = $stripe->customers()->create([
                    'name' => $this->firstname . ' ' . $this->lastname,
                    'email' => $this-> email,
                    'phone' => $this->mobile,
                    'address' => [
                        'line1' => $this->line1,
                        'postal_code' => $this->zipcode,
                        'city' => $this->city,
                        'state' => $this->province,
                        'country' => $this->country
                    ],
                    'shipping' => [
                        'name' => $this->firstname . ' ' . $this->lastname,
                        'address' => [
                            'line1' => $this->line1,
                            'postal_code' => $this->zipcode,
                            'city' => $this->city,
                            'state' => $this->province,
                            'country' => $this->country
                        ],
                    ],
                    'source' => $token['id']
                ]);

                $charge = $stripe->charges()->create([
                    'customer' => $customer['id'],
                    'currency' => 'USD',
                    'amount' => session()->get('checkout')['total']?? True,
                    'description' => 'payment for order no' . $order->id
                ]);
                if($charge['status'] == 'succeeded')
                {
                    $this->makeTransaction($order->id,'approved');
                    $this->resetCart();
                }
                else{
                    session()->flash('stripe_error','Error in Transaction!');
                    $this->thankyou = 0;
                }

            }catch(Exception $e){
                session()->flash('stripe_error',$e->getMessage());
                $this->thankyou = 0;
            }


        }
        $this->sendOrderConfirmationMail($order);
    }


    public function redirectToGateway()
    {
        try {
            return Paystack::getAuthorizationUrl()->redirectNow();
        } catch (\Exception $e) {
            return Redirect::back()->withMessage(['msg' => 'The paystack token has expired. Please refresh the page and try again.', 'type' => 'error']);
        }
    }

    public function handleGatewayCallback()
    {
        $paymentDetails = Paystack::getPaymentData();

        //   dd($paymentDetails);
          $payment = new Payment();

          $payment->email = $paymentDetails['data']['customer']['email'];
          $payment->status = $paymentDetails['data']['status'];
          $payment->amount = $paymentDetails['data']['amount'];
          $payment->trans_id = $paymentDetails['data']['id'];
          $payment->reference = $paymentDetails['data']['reference'];
          $payment->save();

          if($payment->save() && $paymentDetails['data']['status'] == "success") {
            $this->makeTransaction($order->id, 'approved');
            $this->resetCart();
            return redirect('/thank-you');
        }else
         {
           header('Location: error.html');

         }

    }

    public function makeTransaction($order_id, $status)
    {
        $transaction = new Transaction();
        $transaction->user_id = Auth::user()->id;
        $transaction->order_id = $order_id;
        $transaction->mode = $this->paymentmode;
        $transaction->status = 'pending';
        $transaction->save();
    }

    public function sendOrderConfirmationMail($order)
    {
        Mail::to($order->email)->send(new OrderMail($order));
    }

    public function resetCart()
    {
        $this->thankyou = 1;
        Cart::instance('cart')->destroy();
        session()->forget('checkout');
    }

//     public function submit($value)
// {
//     //do your stuff
//    $flag = 1;
// }

    public function verifyForCheckout()
    {
        // $session = session()->get('checkout');
        // dd($session);
        if (!Auth::check()) {
            return redirect()->route('login');
        } else if ($this->thankyou) {
            return redirect()->route('thankyou');
        }
        // else if(!session()->get('checkout'))
        // {
        //      return redirect()->route('product.cart');
        // }
    }



    public function render()
    {

        $this->verifyForCheckout();
        return view('livewire.checkout-component')->layout('layouts.base');
    }
}
