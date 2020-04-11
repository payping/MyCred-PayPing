<?php
function mycred_payping_plugins_loaded()
{
    function Add_PayPing_to_Gateways($installed)
    {
        $installed['payping'] = [

            'title'    => get_option('paypingName') ? get_option('paypingName') : 'درگاه پرداخت پی‌پینگ',
            'callback' => ['myCred_PayPing']
        ];

        return $installed;
    }
	add_filter('mycred_setup_gateways', 'Add_PayPing_to_Gateways');

    function Add_PayPing_to_Buycred_Refs($addons)
    {
        $addons['buy_creds_with_payping'] = __('Buy Cred Purchase', 'mycred');

        return $addons;
    }
	add_filter('mycred_buycred_refs', 'Add_PayPing_to_Buycred_Refs');
	
    function Add_PayPing_to_Buycred_Log_Refs($refs)
    {
        $payping = ['buy_creds_with_payping'];

        return $refs = array_merge($refs, $payping);
    }
	add_filter('mycred_buycred_log_refs', 'Add_PayPing_to_Buycred_Log_Refs');
}
add_action('plugins_loaded', 'mycred_payping_plugins_loaded');

spl_autoload_register('mycred_payping_plugin');

function mycred_payping_plugin()
{
    if (!class_exists('myCRED_Payment_Gateway')) {

        return;
    }

    if (!class_exists('myCred_PayPing')) {

        class myCred_PayPing extends myCRED_Payment_Gateway
        {
            function __construct($gateway_prefs)
            {
                $types = mycred_get_types();
                $default_exchange = [];

                foreach ($types as $type => $label) {

                    $default_exchange[$type] = 1000;
                }

                parent::__construct([

                    'id'       => 'payping',
                    'label'    => get_option('paypingName') ? get_option('paypingName') : 'درگاه پرداخت پی‌پینگ',
                    'defaults' => [

                        'paypingToken'   => null,
                        'paypingName'  => 'درگاه پرداخت پی‌پینگ',
                        'currency'    => 'تومان',
                        'exchange'    => $default_exchange,
                        'item_name'   => __('Purchase of myCRED %plural%', 'mycred'),
                        'mobile'      => null,
                        'description' => null
                    ]
                ], $gateway_prefs);
            }

            public function PayPing_Iranian_currencies($currencies)
            {
                unset($currencies);

                $currencies['ریال'] = 'ریال';
                $currencies['تومان'] = 'تومان';

                return $currencies;
            }

            function preferences()
            {
                add_filter('mycred_dropdown_currencies', [$this, 'PayPing_Iranian_currencies']);

                $prefs = $this->prefs;
                ?>

                <label class="subheader" for="<?php echo $this->field_id('paypingToken'); ?>"><?php _e('توکن', 'mycred'); ?></label>
                <ol>
                    <li>
                        <div class="h2">
                            <input id="<?php echo $this->field_id('paypingToken'); ?>" name="<?php echo $this->field_name('paypingToken'); ?>" type="text" value="<?php echo $prefs['paypingToken']; ?>" class="long"/>
                        </div>
                        <span class="description"><?php _e('توکن دریافتی از سایت پی‌پینگ.', 'mycred'); ?></span>
                    </li>
                </ol>

                <label class="subheader" for="<?php echo $this->field_id('paypingName'); ?>"><?php _e('عنوان', 'mycred'); ?></label>
                <ol>
                    <li>
                        <div class="h2">
                            <input id="<?php echo $this->field_id('paypingName'); ?>" name="<?php echo $this->field_name('paypingName'); ?>" type="text" value="<?php echo $prefs['paypingName'] ? $prefs['paypingName'] : 'درگاه پرداخت پی‌پینگ'; ?>" class="long"/>
                        </div>
                    </li>
                </ol>

                <label class="subheader" for="<?php echo $this->field_id('currency'); ?>"><?php _e('واحد پولی', 'mycred'); ?></label>
                <ol>
                    <li>
                        <?php $this->currencies_dropdown('currency', 'mycred-gateway-payping-currency'); ?>
                    </li>
                </ol>

                <label class="subheader" for="<?php echo $this->field_id('item_name'); ?>"><?php _e('عنوان خرید', 'mycred'); ?></label>
                <ol>
                    <li>
                        <div class="h2">
                            <input id="<?php echo $this->field_id('item_name'); ?>" name="<?php echo $this->field_name('item_name'); ?>" type="text" value="<?php echo $prefs['item_name']; ?>" class="long"/>
                        </div>
                        <span class="description"><?php _e('Description of the item being purchased by the user.', 'mycred'); ?></span>
                    </li>
                </ol>

                <label class="subheader"><?php _e('تبدیل امتیازات', 'mycred'); ?></label>
                <ol>
                    <li>
                        <?php $this->exchange_rate_setup(); ?>
                    </li>
                </ol>
                <?php
            }

            public function sanitise_preferences($data)
            {
                $new_data['paypingToken'] = sanitize_text_field($data['paypingToken']);
                $new_data['paypingName'] = sanitize_text_field($data['paypingName']);
                $new_data['currency'] = sanitize_text_field($data['currency']);
                $new_data['item_name'] = sanitize_text_field($data['item_name']);
                $new_data['mobile'] = sanitize_text_field($data['mobile']);
                $new_data['description'] = sanitize_text_field($data['description']);

                if (isset($data['exchange'])) {

                    foreach ((array)$data['exchange'] as $type => $rate) {

                        if ($rate != 1 && in_array(substr($rate, 0, 1), ['.', ','])) {

                            $data['exchange'][$type] = (float)'0' . $rate;
                        }
                    }
                }

                $new_data['exchange'] = $data['exchange'];

                update_option('paypingName', $new_data['paypingName']);

                return $data;
            }

            public function buy()
            {
                if (!isset($this->prefs['paypingToken']) || empty($this->prefs['paypingToken'])) {

                    wp_die(__('Please setup this gateway before attempting to make a purchase!', 'mycred'));
                }
				

                $type = $this->get_point_type();
                $mycred = mycred($type);

                $amount = $mycred->number($_REQUEST['amount']);
                $amount = abs($amount);
                $cost = $this->get_cost($amount, $type);

                $to = $this->get_to();
                $from = $this->current_user_id;
				
				
                if (isset($_REQUEST['revisit'])) {

                    $payment = strtoupper($_REQUEST['revisit']);

                    $this->transaction_id = $payment;

                } else {

                    $post_id = $this->add_pending_payment([$to, $from, $amount, $cost, $this->prefs['currency'], $type]);
                    $payment = get_the_title($post_id);

                    $this->transaction_id = $payment;
                }

                $item_name = str_replace('%number%', $amount, $this->prefs['item_name']);
                $item_name = $mycred->template_tags_general($item_name);

                $from_user = get_userdata($from);
				
				$TokenCode = $this->prefs['paypingToken'];
				$args = array(
					'timeout'      => 45,
					'redirection'  => '5',
					'httpsversion' => '1.0',
					'blocking'     => true,
					'headers'      => array(
										  'Authorization' => 'Bearer ' . $TokenCode,
										  'Content-Type'  => 'application/json',
										  'Accept'        => 'application/json'
									  ),
					'cookies'      => array()
				);
				
				$callback = add_query_arg('payment_id', $this->transaction_id, $this->callback_url());
				$amount = ($this->prefs['currency'] == 'ریال') ? $cost : ($cost * 10);
				$amount = intval(str_replace(',', '', $amount));
				$mobile = $this->prefs['mobile'];
                $description = $this->prefs['description'];
				
				$params = array(
                        'amount'        => $amount,
                        'returnUrl'     => $callback,
//                        'payerIdentity' => $mobile,
                        'payerName'     => $mobile,
                        'clientRefId'   => $payment,
                        'description'   => $description
                );
				
				$args['body'] = json_encode( $params, true );
				$PayResponse = wp_remote_post( 'https://api.payping.ir/v2/pay', $args );
        		$ResponseXpId = wp_remote_retrieve_headers( $PayResponse )['x-paypingrequest-id'];
				if( is_wp_error( $PayResponse ) ){
					$message = 'خطا در ارتباط به پی‌پینگ : شرح خطا ' . $PayResponse->get_error_message() . '<br/> شماره خطای پی‌پینگ: ' . $ResponseXpId;
					return $message;
				}else{
					$code = wp_remote_retrieve_response_code( $PayResponse );
					if( $code === 200 ){
						if ( isset( $PayResponse["body"] ) && $PayResponse["body"] != '' ) {
							$CodePay = wp_remote_retrieve_body( $PayResponse );
							$CodePay =  json_decode( $CodePay, true );
							wp_redirect( sprintf( '%s/%s', $this->GoToIpgUrl, $CodePay["code"] ) );
							exit;
						}else{
							$message = ' تراکنش ناموفق بود- کد خطا : ' . $ResponseXpId;
							return $message;
						}
					}elseif( $code == 400 ){
						$message = wp_remote_retrieve_body( $PayResponse ) . '<br /> کد خطا: ' . $ResponseXpId;
						return $message;
					}else{
						$message = wp_remote_retrieve_body( $PayResponse ) . '<br /> کد خطا: ' . $ResponseXpId;
						return $message;
					}
				}
            }

            public function process()
            {
                $fault = false;

                if (isset($_REQUEST['payment_id']) && isset($_REQUEST['mycred_call']) && $_REQUEST['mycred_call'] == 'payping') {

                    $pending_post_id = sanitize_text_field($_REQUEST['payment_id']);
                    $org_pending_payment = $pending_payment = $this->get_pending_payment($pending_post_id);
					
					$card_number = isset($result->cardNumber) ? sanitize_text_field($result->cardNumber) : 'Null';
					$cost = (str_replace(',', '', $pending_payment['cost']));
					$cost = (int)$cost;
					$amount = ($this->prefs['currency'] == 'ریال') ? $cost : ($cost * 10);
					$TokenCode = $this->prefs['paypingToken'];
					
					$VarifyData = array( 'refId' => $_POST['refid'], 'amount' => $amount );
					$VarifyArgs = array(
						'body' => json_encode( $VarifyData ),
						'timeout' => '45',
						'redirection' => '5',
						'httpsversion' => '1.0',
						'blocking' => true,
						'headers' => array(
							'Authorization' => 'Bearer ' . $TokenCode,
							'Content-Type' => 'application/json',
							'Accept' => 'application/json'
						),
						'cookies' => array()
					);
					$VerifyResponse = wp_remote_post( 'https://api.payping.ir/v2/pay/verify', $VarifyArgs );
					$ResponseXpId = wp_remote_retrieve_headers( $VerifyResponse )['x-paypingrequest-id'];
					if( is_wp_error( $VerifyResponse ) ){
						$fault = true;
						$message = 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$VerifyResponse->get_error_message();
						return array( 'status' => $Status, 'message' => $message, 'fault' => $fault );
					}else{
						$code = wp_remote_retrieve_response_code( $VerifyResponse );
						if( $code === 200 ){
							if( isset( $_POST["refid"] ) and $_POST["refid"] != '' ){
								$Transaction_ID = $_POST["refid"];
								$message = 'تراکنش موفق، شماره تراکنش: ' . $Transaction_ID;
								$this->log_call($pending_post_id, [__($message, 'mycred')]);
								wp_redirect($this->get_thankyou());
								exit;
							}else{
								$fault = true;
								$message = 'متافسانه سامانه قادر به دریافت کد پیگیری نمی باشد!<br/>نتیجه درخواست: ' .wp_remote_retrieve_body( $response ) . '<br/> شماره خطا: ' . $ResponseXpId;
								$this->log_call($pending_post_id, [__($message, 'mycred')]);
							}
						}else{
							$fault = true;
							$message = wp_remote_retrieve_body( $VerifyResponse );
							$this->log_call($pending_post_id, [__($message, 'mycred')]);
						}
					}

                } else {
                    $fault = true;
                    wp_redirect($this->get_cancelled(''));
                    exit;
                }

                if ($fault) {

                    wp_redirect($this->get_cancelled(''));
                    exit;
                }
            }

            public function returning()
            {
                if (isset($_REQUEST['payment_id']) && isset($_REQUEST['mycred_call']) && $_REQUEST['mycred_call'] == 'payping') {

                    // Returning Actions
                }
            }
        }
    }
}
