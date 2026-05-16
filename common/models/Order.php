<?php

namespace common\models;

use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;
use yii\behaviors\AttributeBehavior;
use borales\extensions\phoneInput\PhoneInputValidator;
use yii\helpers\ArrayHelper;
use yii\web\BadRequestHttpException;

/**
 * This is the model class for table "order".
 *
 * @property string $order_uuid
 * @property string $payment_uuid
 * @property int $customer_id
 * @property string|null $restaurant_uuid
 * @property int $area_id
 * @property int $state_id
 * @property int|null $delivery_zone_id
 * @property int|null $shipping_country_id
 * @property string|null $country_name
 * @property string|null $country_name_ar
 * @property string|null $business_location_name
 * @property string|null $floor
 * @property string|null $apartment
 * @property string|null $office
 * @property string|null $postalcode
 * @property string|null $address_1
 * @property string|null $address_2
 * @property string $area_name
 * @property string $area_name_ar
 * @property string $unit_type
 * @property string $block
 * @property string $street
 * @property string $avenue
 * @property string $house_number
 * @property string $special_directions
 * @property string $customer_name
 * @property string $customer_phone_number
 * @property string $customer_phone_country_code
 * @property string $customer_email
 * @property int $payment_method_id
 * @property string $payment_method_name
 * @property string $payment_method_name_ar
 * @property string $currency_code
 * @property string $store_currency_code
 * @property string $currency_rate
 * @property int|null $order_status
 * @property int is_deleted
 * @property int $order_mode
 * @property int $subtotal
 * @property int $tax
 * @property int $sms_sent
 * @property int $total_price
 * @property int $items_has_been_restocked
 * @property int $latitude
 * @property int $longitude
 * @property boolean $is_order_scheduled
 * @property datetime $scheduled_time_start_from
 * @property datetime $scheduled_time_to
 * @property string $armada_tracking_link
 * @property string $aramex_shipment_id
 * @property string $aramex_pickup_id
 * @property string $armada_qr_code_link
 * @property int|null $voucher_id
 * @property int $subtotal_before_refund
 * @property int $total_price_before_refund
 * @property int $restaurant_branch_id
 * @property int $pickup_location_id
 * @property datetime $order_created_at
 * @property datetime $order_updated_at
 * @property int|null $bank_discount_id
 * @property string $mashkor_tracking_link
 * @property string $mashkor_driver_name
 * @property string $mashkor_driver_phone
 * @property string $mashkor_order_status
 * @property string $recipient_name
 * @property string $sender_name
 * @property string $recipient_phone_number
 * @property string $gift_message
 * @property string $order_instruction
 * @property boolean $reminder_sent
 * @property boolean estimated_time_of_arrival
 * @property string $diggipack_awb_no
 * @property boolean $is_sandbox
 * @property boolean $is_market_order
 * @property decimal $total
 * @property Area
 * @property BankDiscount $bankDiscount
 * @property Country $country
 * @property DeliveryZone $deliveryZone
 * @property RestaurantBranch $restaurantBranch
 * @property Customer $customer
 * @property PaymentMethod $paymentMethod
 * @property Restaurant $restaurant
 * @property RestaurantDelivery $restaurantDelivery
 * @property Payment $payment
 * @property Voucher $voucher
 * @property Refund[] $refunds
 * @property RefundedItem[] $refundedItems
 * @property OrderItem[] $orderItems
 */
class Order extends \yii\db\ActiveRecord
{
    //Values for `order_status`
    const STATUS_DRAFT = 0;
    const STATUS_PENDING = 1;
    const STATUS_BEING_PREPARED = 2;
    const STATUS_OUT_FOR_DELIVERY = 3;
    const STATUS_COMPLETE = 4;
    const STATUS_CANCELED = 5;
    const STATUS_PARTIALLY_REFUNDED = 6;
    const STATUS_REFUNDED = 7;
    const STATUS_ABANDONED_CHECKOUT = 9;
    const STATUS_ACCEPTED = 10;

    //Values for `order_mode`
    const ORDER_MODE_DELIVERY = 1;
    const ORDER_MODE_PICK_UP = 2;

    const UNIT_TYPE_OFFICE = 'office';
    const UNIT_TYPE_HOUSE = 'house';
    const UNIT_TYPE_APARTMENT = 'apartment';

    //Values for `mashkor_order_status`
    const MASHKOR_ORDER_STATUS_NEW = 0;
    const MASHKOR_ORDER_STATUS_CONFIRMED = 1;
    const MASHKOR_ORDER_STATUS_ASSIGNED = 2;
    const MASHKOR_ORDER_STATUS_PICKUP_STARTED = 3;
    const MASHKOR_ORDER_STATUS_PICKED_UP = 4;
    const MASHKOR_ORDER_STATUS_IN_DELIVERY = 5;
    const MASHKOR_ORDER_STATUS_ARRIVED_DESTINATION = 6;
    const MASHKOR_ORDER_STATUS_DELIVERED = 10;
    const MASHKOR_ORDER_STATUS_CANCELED = 11;

    const SCENARIO_APPLY_VOUCHER = 'apply-voucher';
    const SCENARIO_UPDATE_ARMADA_STATUS = 'update-armada-status';
    const SCENARIO_UPDATE_MASHKOR_STATUS = 'update-mashkor-status';
    const SCENARIO_UPDATE_ARMADA = 'update-armada';
    const SCENARIO_UPDATE_MASHKOR = 'update-mashkor';
    const SCENARIO_UPDATE_TOTAL = 'updateTotal';
    const SCENARIO_CREATE_ORDER_BY_ADMIN = 'manual';
    const SCENARIO_INIT_ORDER = 'init-order';
    const SCENARIO_OLD_VERSION = 'old_version';
    const SCENARIO_UPDATE_STATUS = 'updateStatus';
    const SCENARIO_DELETE = 'delete';

    public $civil_id = null;
    public $section = null;
    public $class = null;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'order';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            // 'currency_code', 'store_currency_code', 'payment_method_name', 'payment_method_id',
            [['customer_name', 'order_mode', 'total_price', 'subtotal',
                'order_mode', 'restaurant_uuid', 'customer_phone_number', 'customer_phone_country_code', 'customer_name'], 'required'],

            [['is_order_scheduled'], 'required', 'on' => 'create'],

            [['is_market_order', 'is_sandbox'], 'boolean'],

            [['payment_method_id'], 'required', 'except' => [
                self::SCENARIO_CREATE_ORDER_BY_ADMIN,
                self::SCENARIO_INIT_ORDER
            ]],
            [['order_uuid'], 'string', 'max' => 40],
            [['order_uuid'], 'unique'],
            [['area_id', 'state_id', 'payment_method_id', 'order_status', 'mashkor_order_status', 'customer_id', 'is_deleted'], 'integer', 'min' => 0],
            [['items_has_been_restocked', 'is_order_scheduled', 'voucher_id', 'reminder_sent', 'sms_sent', 'customer_phone_country_code', 'delivery_zone_id', 'shipping_country_id', 'pickup_location_id'], 'integer'],

            ['mashkor_order_status', 'in', 'range' => [
                self::MASHKOR_ORDER_STATUS_NEW,
                self::MASHKOR_ORDER_STATUS_CONFIRMED,
                self::MASHKOR_ORDER_STATUS_ASSIGNED,
                self::MASHKOR_ORDER_STATUS_PICKUP_STARTED,
                self::MASHKOR_ORDER_STATUS_PICKED_UP,
                self::MASHKOR_ORDER_STATUS_IN_DELIVERY,
                self::MASHKOR_ORDER_STATUS_ARRIVED_DESTINATION,
                self::MASHKOR_ORDER_STATUS_DELIVERED,
                self::MASHKOR_ORDER_STATUS_CANCELED,
            ]],

            [['customer_phone_number'], 'required', 'on' => self::SCENARIO_CREATE_ORDER_BY_ADMIN],

            //todo: not accepting indian number 8758702738
            //[['customer_phone_number'], PhoneInputValidator::className (), 'message' => 'Please insert a valid phone number', 'except' => self::SCENARIO_OLD_VERSION],

            ['order_status', 'in', 'range' => [self::STATUS_PENDING, self::STATUS_BEING_PREPARED, self::STATUS_OUT_FOR_DELIVERY, self::STATUS_COMPLETE, self::STATUS_REFUNDED, self::STATUS_PARTIALLY_REFUNDED, self::STATUS_CANCELED, self::STATUS_DRAFT, self::STATUS_ABANDONED_CHECKOUT, self::STATUS_ACCEPTED]],

            ['order_mode', 'in', 'range' => [self::ORDER_MODE_DELIVERY, self::ORDER_MODE_PICK_UP]],

            ['pickup_location_id', function ($attribute, $params, $validator) {
                if (!$this->pickup_location_id && $this->order_mode == Order::ORDER_MODE_PICK_UP)
                    $this->addError($attribute, Yii::t('app', 'Branch name cannot be blank.'));
            }, 'skipOnError' => false, 'skipOnEmpty' => false],

            ['delivery_zone_id', function ($attribute, $params, $validator) {

                if($this->order_mode != Order::ORDER_MODE_DELIVERY) {
                    return true;
                }

                if (!$this->delivery_zone_id)
                    $this->addError($attribute, Yii::t('app', 'Delivery zone cannot be blank.'));

            }, 'skipOnError' => false, 'skipOnEmpty' => false],

            [['scheduled_time_start_from', 'scheduled_time_to'], function ($attribute, $params, $validator) {
                if ($this->is_order_scheduled && (!$this->scheduled_time_start_from || !$this->scheduled_time_to))
                    $this->addError($attribute, Yii::t('app', '{attribute} cannot be blank.',[
                        'attribute' => $attribute
                    ]));
            }, 'skipOnError' => false, 'skipOnEmpty' => false],

            //TODO
            // ['area_id', function ($attribute, $params, $validator) {
            //         if ($this->order_mode == Order::ORDER_MODE_DELIVERY && $this->shipping_country_id && !$this->area_id)
            //             $this->addError($attribute, 'Area name cannot be blank.');
            //     }, 'skipOnError' => false, 'skipOnEmpty' => false],
            // ['shipping_country_id', function ($attribute, $params, $validator) {
            //         if ($this->order_mode == Order::ORDER_MODE_DELIVERY && $this->shipping_country_id && !$this->area_id)
            //             $this->addError($attribute, 'Area name cannot be blank.');
            //     }, 'skipOnError' => false, 'skipOnEmpty' => false],
            ['shipping_country_id', 'validateCountry', 'when' => function ($model) {
                return $model->order_mode == static::ORDER_MODE_DELIVERY;
            }
            ],
            [['area_id'], 'validateArea'],
            [['pickup_location_id'], 'validatePickupLocation'],
            ['unit_type', function ($attribute, $params, $validator) {
                if ($this->area_id && !$this->unit_type && $this->order_mode == Order::ORDER_MODE_DELIVERY) {
                    $this->addError($attribute, Yii::t('app','Unit type cannot be blank.'));
                }
            }, 'skipOnError' => false, 'skipOnEmpty' => false],

            /*['block', function ($attribute, $params, $validator) {
                if ($this->area_id && $this->block == null && $this->order_mode == Order::ORDER_MODE_DELIVERY)
                {
                    $this->addError($attribute, Yii::t('app','Block cannot be blank.'));
                }
            }, 'skipOnError' => false, 'skipOnEmpty' => false],*/

            ['street', function ($attribute, $params, $validator) {
                if ($this->area_id && $this->street == null && $this->order_mode == Order::ORDER_MODE_DELIVERY)
                {
                    $this->addError($attribute, Yii::t('app','Street cannot be blank.'));
                }
            }, 'skipOnError' => false, 'skipOnEmpty' => false],

            /*
             * commenting temporary for frontend validation error
             * ['house_number', function ($attribute, $params, $validator) {
                if ($this->area_id && strtolower($this->unit_type) != self::UNIT_TYPE_OFFICE &&
                    $this->house_number == null
                    && $this->order_mode == Order::ORDER_MODE_DELIVERY)
                {
                    $this->addError($attribute, Yii::t('app','House number cannot be blank.'));
                }
            }, 'skipOnError' => false, 'skipOnEmpty' => false],
*/
            [['floor'], 'required', 'when' => function ($model) {
                return $model->unit_type && strtolower($model->unit_type) == self::UNIT_TYPE_APARTMENT &&
                    $model->restaurant->version > 1 && $this->order_mode == Order::ORDER_MODE_DELIVERY;
            }],//(strtolower($model->unit_type) == self::UNIT_TYPE_OFFICE ||

            [['office'], 'required', 'when' => function ($model) {
                return $model->unit_type && strtolower($model->unit_type) == self::UNIT_TYPE_OFFICE &&
                    $model->restaurant->version > 1 && $this->order_mode == Order::ORDER_MODE_DELIVERY;
            }
            ],

            [['apartment'], 'required', 'when' => function ($model) {
                return $model->unit_type && strtolower($model->unit_type) == self::UNIT_TYPE_APARTMENT &&
                    $model->restaurant->version > 1 && $this->order_mode == Order::ORDER_MODE_DELIVERY;
            }],

            ['order_mode', 'validateOrderMode', 'except' => self::SCENARIO_CREATE_ORDER_BY_ADMIN],
            [['restaurant_uuid'], 'string', 'max' => 60],
            [['customer_phone_number'], 'string', 'min' => 6, 'max' => 20],
            [['customer_phone_number'], 'number'],
            [['total_price', 'total_price_before_refund', 'delivery_fee', 'subtotal', 'subtotal_before_refund', 'tax'], 'number', 'min' => 0],
            ['subtotal', 'validateMinCharge', 
                'except' => self::SCENARIO_CREATE_ORDER_BY_ADMIN, 
                'when' => function ($model) {
                    return $model->order_mode == static::ORDER_MODE_DELIVERY && $model->subtotal > 0;
                }
            ],
            /*[['postalcode', 'city', 'address_1', 'address_2'], 'required', 'when' => function ($model) {
                return $model->shipping_country_id;
            }
            ],*/
            // [
            //     'subtotal', function ($attribute, $params, $validator) {
            //     if ($this->voucher && $this->voucher->minimum_order_amount !== 0 && $this->calculateOrderItemsTotalPrice() >= $this->voucher->minimum_order_amount)
            //         $this->addError('voucher_id', Yii::t('app', "We can't apply this code until you reach the minimum order amount"));
            // }, 'skipOnError' => false, 'skipOnEmpty' => false
            // ],
            [['customer_email'], 'email'],
            [['payment_method_id'], 'validatePaymentMethodId', 'except' => self::SCENARIO_CREATE_ORDER_BY_ADMIN],
            [['payment_method_id'], 'default', 'value' => 3, 'on' => self::SCENARIO_CREATE_ORDER_BY_ADMIN],
            ['items_has_been_restocked', 'default', 'value' => false],
            [['voucher_id'], 'validateVoucherId', 'except' => self::SCENARIO_CREATE_ORDER_BY_ADMIN],
            [['payment_uuid'], 'string', 'max' => 36],
            [['estimated_time_of_arrival', 'scheduled_time_start_from', 'scheduled_time_to', 'latitude', 'longitude', 'restaurant_branch_id','order_instruction'], 'safe'],
            [['payment_uuid'], 'exist', 'skipOnError' => true, 'targetClass' => Payment::className(), 'targetAttribute' => ['payment_uuid' => 'payment_uuid']],

            [
                [
                    'area_name', 'area_name_ar', 'unit_type', 'block', 'street', 'avenue', 'house_number', 'special_directions',
                    'customer_name', 'customer_email',
                    'payment_method_name', 'payment_method_name_ar',
                    'armada_tracking_link',
                    'aramex_shipment_id',
                    'aramex_pickup_id',
                    'armada_qr_code_link', 'armada_delivery_code',
                    'country_name', 'country_name_ar',
                    'business_location_name',
                    'building', 'apartment', 'city', 'address_1', 'address_2', 'postalcode', 'floor', 'office',
                    'recipient_name', 'recipient_phone_number', 'gift_message',
                    'currency_code', 'store_currency_code', 'currency_rate', 'sender_name',
                    'armada_order_status', 'diggipack_awb_no'
                ],
                'safe'
            ],

            [['postalcode'], 'string', 'max' => 10],

            [['civil_id', 'section', 'class'], 'string', 'max' => 255], //Temp var

            [['mashkor_order_number', 'mashkor_tracking_link', 'mashkor_driver_name', 'mashkor_driver_phone', 'aramex_shipment_id', 'aramex_pickup_id'], 'string', 'max' => 255],

            [['delivery_zone_id'], 'exist', 'skipOnError' => false, 'targetClass' => DeliveryZone::className(),
                'targetAttribute' => ['delivery_zone_id' => 'delivery_zone_id', 'restaurant_uuid']],
            [['shipping_country_id'], 'exist', 'skipOnError' => false, 'targetClass' => Country::className(), 'targetAttribute' => ['shipping_country_id' => 'country_id']],
            [['area_id'], 'exist', 'skipOnError' => false, 'targetClass' => Area::className(), 'targetAttribute' => ['area_id' => 'area_id']],
            [['state_id'], 'exist', 'skipOnError' => false, 'targetClass' => State::className(), 'targetAttribute' => ['state_id' => 'state_id']],
            [['bank_discount_id'], 'exist', 'skipOnError' => true, 'targetClass' => BankDiscount::className(), 'targetAttribute' => ['bank_discount_id' => 'bank_discount_id']],
            [['customer_id'], 'exist', 'skipOnError' => false, 'targetClass' => Customer::className(), 'targetAttribute' => ['customer_id' => 'customer_id']],
            [['payment_method_id'], 'exist', 'skipOnError' => false, 'targetClass' => PaymentMethod::className(), 'targetAttribute' => ['payment_method_id' => 'payment_method_id']],
            [['restaurant_uuid'], 'exist', 'skipOnError' => false, 'targetClass' => Restaurant::className(), 'targetAttribute' => ['restaurant_uuid' => 'restaurant_uuid']],
            [['restaurant_branch_id'], 'exist', 'skipOnError' => true, 'targetClass' => RestaurantBranch::className(),
                'targetAttribute' => ['restaurant_branch_id' => 'restaurant_branch_id', 'restaurant_uuid']],
            [['pickup_location_id'], 'exist', 'skipOnError' => true, 'targetClass' => BusinessLocation::className(),
                'targetAttribute' => ['pickup_location_id' => 'business_location_id', 'restaurant_uuid']],
            [['voucher_id'], 'exist', 'skipOnError' => true, 'targetClass' => Voucher::className(),
                'targetAttribute' => ['voucher_id' => 'voucher_id', 'restaurant_uuid']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => 'order_uuid',
                ],
                'value' => function () {
                    if (!$this->order_uuid) {
                        // Get a unique uuid from payment table
                        $this->order_uuid = strtoupper(Order::getUniqueOrderUuid());
                    }

                    return $this->order_uuid;
                }
            ],
            [

                'class' => \borales\extensions\phoneInput\PhoneInputBehavior::className(),
                // 'attributes' => [
                //           ActiveRecord::EVENT_BEFORE_INSERT => ['customer_phone_number', 'customer_phone_country_code'],
                //       ],
                'countryCodeAttribute' => 'customer_phone_country_code',
                'phoneAttribute' => 'customer_phone_number',
            ],
            [
                'class' => TimestampBehavior::className(),
                'createdAtAttribute' => 'order_created_at',
                'updatedAtAttribute' => 'order_updated_at',
                'value' => new Expression('NOW()'),
            ]
        ];
    }

    /**
     * Get a unique alphanumeric uuid to be used for a payment
     * @return string uuid
     */
    private static function getUniqueOrderUuid($length = 6)
    {
        $uuid = Yii::$app->security->generateRandomString ($length);
            //\ShortCode\Random::get($length);

        $isNotUnique = static::find()->where(['order_uuid' => $uuid])->exists();

        // If not unique, try again recursively
        if ($isNotUnique) {
            return static::getUniqueOrderUuid($length);
        }

        return $uuid;
    }

    /**
     * @inheritdoc
     */
    // public function fields()
    // {
    //     $fields = parent::fields ();
    //
    //     // remove fields that contain sensitive information
    //     unset($fields['armada_delivery_code']);
    //     unset($fields['mashkor_order_number']);
    //     unset($fields['mashkor_tracking_link']);
    //     unset($fields['mashkor_driver_name']);
    //     unset($fields['mashkor_driver_phone']);
    //     unset($fields['mashkor_order_status']);
    //     unset($fields['armada_tracking_link']);
    //     unset($fields['reminder_sent']);
    //     unset($fields['sms_sent']);
    //     unset($fields['items_has_been_restocked']);
    //     unset($fields['subtotal_before_refund']);
    //     unset($fields['total_price_before_refund']);
    //
    //     return $fields;
    //
    // }

    public function fields() {
        return array_merge(parent::fields(), [
            'delivery_fee' => function ($order) {
                return (float) $order->delivery_fee;
            },
            'discount_type' => function ($order) {
                return (int) $order->discount_type;
            },
            'subtotal' => function ($order) {
                return (float) $order->subtotal;
            },
            'tax' => function ($order) {
                return (float) $order->tax;
            },
            'total_price' => function ($order) {
                return (float) $order->total_price;
            }
        ]);
    }

    /**
     * @inheritdoc
     */
    public function extraFields()
    {
        return [
            'orderStatusInEnglish',
            'orderStatusInArabic',
            'restaurant',
            'orderItems',
            'area',
            'state',
            'country',
            /*'orderItems' => function ($order) {
                return $order->getOrderItems()
                    ->with('orderItemExtraOptions')
                    ->asArray()
                    ->all();
            },*/
            'restaurantBranch',
            'deliveryZone',
            'pickupLocation',
            'businessLocation',
            'payment',
            'currency',
            'refundedTotal',
            'voucher',
            'paymentMethod'
        ];
    }

    /**
     * Returns String value of current status
     * @return string
     */
    public function getOrderStatusInEnglish()
    {
        switch ($this->order_status) {
            case self::STATUS_DRAFT:
                return "Draft";
                break;
            case self::STATUS_PENDING:
                return "Pending";
                break;
            case self::STATUS_BEING_PREPARED:
                return "Being Prepared";
                break;
            case self::STATUS_OUT_FOR_DELIVERY:
                return "Out for Delivery";
                break;
            case self::STATUS_COMPLETE:
                return "Complete";
                break;
            case self::STATUS_CANCELED:
                return "Canceled";
                break;
            case self::STATUS_PARTIALLY_REFUNDED:
                return "Partially Refunded";
                break;
            case self::STATUS_REFUNDED:
                return "Refunded";
                break;
            case self::STATUS_ACCEPTED:
                return "Accepted";
                break;
            case self::STATUS_ABANDONED_CHECKOUT:
                return "Abandoned";
                break;
        }
    }

    /**
     * Returns String value of current status
     * @return string
     */
    public function getorderStatusInArabic()
    {
        switch ($this->order_status) {
            case self::STATUS_PENDING:
                return "قيد الانتظار";
                break;
            case self::STATUS_BEING_PREPARED:
                return "يجري الاستعداد للطلب";
                break;
            case self::STATUS_OUT_FOR_DELIVERY:
                return "خارج للتوصيل";
                break;
            case self::STATUS_COMPLETE:
                return "تم الاستلام";
                break;
            case self::STATUS_CANCELED:
                return "تم إلغاء الطلب";
                break;
            case self::STATUS_PARTIALLY_REFUNDED:
                return "مسترد جزئيا";
                break;
            case self::STATUS_REFUNDED:
                return "مسترد";
                break;
            case self::STATUS_ACCEPTED:
                return "تم قبول الطلب";
                break;
        }
    }

    /**
     * Check if the selected payment method id is exist in restaurant_payment_method
     * @param type $attribute
     */
    public function validatePaymentMethodId($attribute)
    {
        if (!RestaurantPaymentMethod::find()->where(['restaurant_uuid' => $this->restaurant_uuid, 'payment_method_id' => $this->payment_method_id])->one())
            $this->addError($attribute, Yii::t('app',"Payment method id invalid."));
    }

    /**
     * Validate promo code
     * @param type $attribute
     */
    public function validateVoucherId($attribute)
    {
        $voucher = Voucher::find()->where([
            'restaurant_uuid' => $this->restaurant_uuid,
            'voucher_id' => $this->voucher_id,
            'voucher_status' => Voucher::VOUCHER_STATUS_ACTIVE
        ])->exists();

        if (!$voucher || !$this->voucher->isValid($this->customer_phone_number))
        {
            $this->addError($attribute, Yii::t('app',"Voucher code is invalid or expired"));
            return null;
        }

        $discountedItems =  $this->getItems ()->andWhere (['>', 'compare_at_price', 0])->count();

        if($this->voucher->exclude_discounted_items && $discountedItems > 0)
        {
            $this->addError($attribute, Yii::t('app',"Voucher code can not be applied for discounted items"));
            return null;
        }
    }

    /**
     * Check if the selected area delivery by the restaurant or no
     * @param type $attribute
     */
    public function validateArea($attribute)
    {
        $query = AreaDeliveryZone::find()
            ->where([
                'restaurant_uuid' => $this->restaurant_uuid,
                'delivery_zone_id' => $this->delivery_zone_id
            ]);

        if($this->area_id) {
            //if delivery to whole country or delivering to selected area
            $query->andWhere(new Expression('area_id IS NULL OR area_id="' . $this->area_id . '"'));
        }

        if($this->state_id) {
            $query->andWhere(['state_id' => $this->state_id]);
        }

        /*
        TODO: validate city for outside of Kuwait order
        if($this->city_id) {
            $query->andWhere(['city_id' => $this->city_id]);
        }*/

        if (!$query->exists())
            $this->addError($attribute, Yii::t('app',"Store does not deliver to this delivery zone."));
    }

    /**
     * Check if the pickup location is exist
     * @param type $attribute
     */
    public function validatePickupLocation($attribute)
    {
        if($this->order_mode == self::ORDER_MODE_DELIVERY)
            return true;

        $model = BusinessLocation::find()
            ->where([
                'restaurant_uuid' => $this->restaurant_uuid,
                'business_location_id' => $this->pickup_location_id]
            )->one();

        if (!$model)
            $this->addError($attribute, "Pickup location doesn't exist");
    }

    /**
     * Check if  store deliver to the selected country
     * @param type $attribute
     */
    public function validateCountry($attribute)
    {
        return true;//todo: why?
        $areaDeliveryZone = AreaDeliveryZone::find()->where(['country_id' => $this->shipping_country_id, 'delivery_zone_id' => $this->delivery_zone_id])->one();

        if (!$areaDeliveryZone || $areaDeliveryZone->area_id != null || ($areaDeliveryZone && $areaDeliveryZone->businessLocation->restaurant_uuid != $this->restaurant_uuid))
            $this->addError($attribute, Yii::t('app',"Store does not deliver to this area."));
    }

    /**
     * Validate order mode attribute
     * @param type $attribute
     */
    public function validateOrderMode($attribute)
    {
        if ($this->$attribute == static::ORDER_MODE_DELIVERY && !$this->delivery_zone_id) {
            $this->addError($attribute, Yii::t('app', "Store doesn't accept delivery"));
        }

        else if ($this->$attribute == static::ORDER_MODE_PICK_UP && $this->pickup_location_id && $this->pickupLocation && !$this->pickupLocation->support_pick_up) {
            $this->addError($attribute, Yii::t('app', "Store doesn't accept pick up"));
        }
    }

    /**
     * Validates min charge
     * This method serves as the inline validation for min_charge.
     *
     * @param string $attribute the attribute currently being validated
     */
    public function validateMinCharge($attribute)
    {
        if (!$this->deliveryZone) {
            return $this->addError(
                $attribute,
                Yii::t('yii', "{attribute} is invalid.", [
                    'attribute' => Yii::t('app', 'Delivery Zone')
                ])
            );
        }

        if ($this->deliveryZone->min_charge > $this->$attribute) {

            if($this->currency && $this->currency->code) {
                $this->addError($attribute, Yii::t('app', "Minimum Order Amount: {amount}", [
                    'amount' => \Yii::$app->formatter->asCurrency(
                        $this->deliveryZone->min_charge,
                        $this->currency->code,
                        [\NumberFormatter::MAX_FRACTION_DIGITS => $this->currency->decimal_place]
                    )
                ]));

            } else if($this->currency_code) {
                $this->addError($attribute, Yii::t('app', "Minimum Order Amount: {amount}", [
                    'amount' => \Yii::$app->formatter->asCurrency($this->deliveryZone->min_charge, $this->currency_code)
                ]));
            }
            else
            {
                $this->addError($attribute, Yii::t('app', "Minimum Order Amount: {amount}", [
                    'amount' => $this->deliveryZone->min_charge
                ]));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'order_uuid' => Yii::t('app','Order UUID'),
            'payment_uuid' => Yii::t('app','Payment Uuid'),
            'restaurant_uuid' => Yii::t('app','Restaurant Uuid'),
            'area_id' => Yii::t('app','Area ID'),
            'state_id' => Yii::t('app','State ID'),
            'shipping_country_id' => Yii::t('app','Country ID'),
            'delivery_zone_id' => Yii::t('app','Delivery Zone ID'),
            'country_name' => Yii::t('app','Country Name'),
            'country_name_ar' => Yii::t('app','Country Name Ar'),
            'business_location_name' => Yii::t('app','Branch'),
            'floor' =>Yii::t('app', 'Floor'),
            'apartment' => Yii::t('app','Apartment'),
            'office' => Yii::t('app','Office'),
            'building' => Yii::t('app','Building'),
            'postalcode' => Yii::t('app','Postal code'),
            'address_1' => Yii::t('app','Address line 1'),
            'address_2' => Yii::t('app','Address line 2'),
            'area_name' => Yii::t('app','Area name'),
            'area_name_ar' => Yii::t('app','Area name in Arabic'),
            'unit_type' => Yii::t('app','Unit type'),
            'block' => Yii::t('app','Block'),
            'street' => Yii::t('app','Street'),
            'customer_id' => Yii::t('app','Customer ID'),
            'avenue' => Yii::t('app','Avenue'),
            'house_number' => Yii::t('app','House number'),
            'special_directions' => Yii::t('app','Special directions'),
            'customer_name' => Yii::t('app','Customer name'),
            'customer_phone_country_code' => Yii::t('app','Country Code'),
            'customer_phone_number' => Yii::t('app','Phone number'),
            'customer_email' => Yii::t('app','Customer email'),
            'payment_method_id' =>Yii::t('app', 'Payment method ID'),
            'payment_method_name' =>Yii::t('app', 'Payment method name'),
            'payment_method_name_ar' => Yii::t('app','Payment method name [Arabic]'),
            'currency_code' => Yii::t('app','Currency Code'),
            'store_currency_code' => Yii::t('app','Store Currency Code'),
            'currency_rate' => Yii::t('app','Currency Rate'),
            'order_status' => Yii::t('app','Status'),
            'total_price' => Yii::t('app','Price'),
            'total_price_before_refund' => Yii::t('app','Total price before refund'),
            'subtotal' => Yii::t('app','Subtotal'),
            'order_created_at' => Yii::t('app','Created at'),
            'order_updated_at' => Yii::t('app','Updated at'),
            'armada_tracking_link' => Yii::t('app','Tracking link'),
            'aramex_shipment_id' => Yii::t('app','Aramex Shipment ID'),
            'aramex_pickup_id' => Yii::t('app','Aramex Pickup ID'),
            'armada_delivery_code' => Yii::t('app','Armada delivery code'),
            'armada_qr_code_link' => Yii::t('app','QR code link'),
            'latitude' => Yii::t('app','Latitude'),
            'longitude' => Yii::t('app','Longitude'),
            'estimated_time_of_arrival' => Yii::t('app','Expected at'),
            'is_order_scheduled' => Yii::t('app','Is order scheduled'),
            'voucher_id' => Yii::t('app','Voucher ID'),
            'tax' => Yii::t('app','Tax'),
            'recipient_name' => Yii::t('app','Recipient name'),
            'sender_name' => Yii::t('app','Sender name'),
            'recipient_phone_number' => Yii::t('app','Recipient phone number'),
            'gift_message' => Yii::t('app','Gift Message'),
            'order_instruction' => Yii::t('app','Order Instruction'),
            'bank_discount_id' => Yii::t('app','Bank discount ID'),
            'mashkor_order_number' => Yii::t('app','Mashkor order number'),
            'mashkor_tracking_link' => Yii::t('app','Mashkor order tracking link'),
            'mashkor_driver_name' => Yii::t('app','Name of the driver'),
            'mashkor_driver_phone' => Yii::t('app','Driver phone number'),
            'mashkor_order_status' => Yii::t('app','Mashkor order status'),
            'is_market_order' => Yii::t('app','Is market order'),
        ];
    }

    /**
     * @return void
     */
    public function sendPaymentConfirmationEmail($payment = null)
    {
        if(!$payment) {
            $payment = Payment::find()
                ->andWhere ([
                    'order_uuid' => $this->order_uuid,
                    'received_callback' => true
                ])
                ->orderBy('payment_updated_at DESC')
                ->one();
        }

        $replyTo = [];
        if ($this->restaurant->restaurant_email) {
            $replyTo = [
                $this->restaurant->restaurant_email => $this->restaurant->name
            ];
        } else if ($this->restaurant->owner_email) {
            $replyTo = [
                $this->restaurant->owner_email => $this->restaurant->name
            ];
        }

        //mailer : override transport if store smtp settings available

        $host = Setting::getConfig($this->restaurant_uuid, 'mail', 'host');
        $username = Setting::getConfig($this->restaurant_uuid, 'mail', 'username');
        $password = Setting::getConfig($this->restaurant_uuid, 'mail', 'password');
        $port = Setting::getConfig($this->restaurant_uuid, 'mail', 'port');
        $encryption = Setting::getConfig($this->restaurant_uuid, 'mail', 'encryption');

        if($host && $username && $password && $port && $encryption)
        {
            $fromEmail = empty($this->restaurant->restaurant_email)?
                \Yii::$app->params['noReplyEmail']: $this->restaurant->restaurant_email;

            \Yii::$app->mailer->setTransport([
                'scheme' => 'smtp',
                'host' => $host,
                'username' => $username,
                'password' => $password,
                'port' => (int) $port,
                'encryption' => $encryption
            ]);
        }
        else
        {
            $fromEmail = \Yii::$app->params['noReplyEmail'];
        }

        if ($this->customer_email) {

            $ml = new MailLog();
            $ml->to = $this->customer_email;
            $ml->from = $fromEmail;
            $ml->subject = 'Order #' . $this->order_uuid . ' from ' . $this->restaurant->name;
            $ml->save();

            $mailer = \Yii::$app->mailer->compose([
                'html' => 'payment-confirm-html',
            ], [
                'order' => $this,
                'payment' => $payment
            ])
                ->setFrom([$fromEmail => $this->restaurant->name])
                //->setFrom($fromEmail)//[$fromEmail => $this->restaurant->name]
                ->setTo($this->customer_email)
                //->setReplyTo(\Yii::$app->params['supportEmail'])
                ->setSubject('Order #' . $this->order_uuid . ' from ' . $this->restaurant->name);
                //->setReplyTo($replyTo)

            if(\Yii::$app->params['elasticMailIpPool'])
                $mailer->setHeader ("poolName", \Yii::$app->params['elasticMailIpPool']);

            try {
                $mailer->send();
            } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
                // Handle email transport-specific exceptions
                Yii::error( "Failed to send email: " . $e->getMessage());
            } catch (\Exception $e) {
                // Handle any other exceptions
                Yii::error( "An error occurred: " . $e->getMessage());
            }
        }

        $emailSentTo = [];

        foreach ($this->restaurant->getAgentAssignments()->all() as $agentAssignment) {

            if (!$agentAssignment->agent) {
                continue;
            }

            if ($agentAssignment->email_notification) {

                $ml = new MailLog();
                $ml->to = $agentAssignment->agent->agent_email;
                $ml->from = $fromEmail;
                $ml->subject = 'Order #' . $this->order_uuid . ' from ' . $this->restaurant->name;
                $ml->save();

                $mailer = \Yii::$app->mailer->compose([
                    'html' => 'payment-confirm-html',
                ], [
                    'order' => $this
                ])
                    ->setFrom([$fromEmail => $this->restaurant->name])
                    //->setFrom($fromEmail)//[$fromEmail => $this->restaurant->name]
                    ->setTo($agentAssignment->agent->agent_email)
                    //->setReplyTo(\Yii::$app->params['supportEmail'])
                    ->setSubject('Order #' . $this->order_uuid . ' from ' . $this->restaurant->name);
                    //->setReplyTo($replyTo)

                if(\Yii::$app->params['elasticMailIpPool'])
                    $mailer->setHeader ("poolName", \Yii::$app->params['elasticMailIpPool']);

                try {
                    $mailer->send();
                    $emailSentTo[] = $agentAssignment->agent->agent_email;
                } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
                    // Handle email transport-specific exceptions
                    Yii::error( "Failed to send email: " . $e->getMessage());
                } catch (\Exception $e) {
                    // Handle any other exceptions
                    Yii::error( "An error occurred: " . $e->getMessage());
                }
            }
        }

        if (
            $this->restaurant->restaurant_email_notification &&
            $this->restaurant->restaurant_email &&
            !in_array($this->restaurant->restaurant_email, $emailSentTo)
        ) {
            $ml = new MailLog();
            $ml->to = $this->restaurant->restaurant_email;
            $ml->from = $fromEmail;
            $ml->subject = 'Order #' . $this->order_uuid . ' from ' . $this->restaurant->name;
            $ml->save();

            $mailer = \Yii::$app->mailer->compose([
                    'html' => 'payment-confirm-html',
                ], [
                    'order' => $this
                ])
                ->setFrom([$fromEmail => $this->restaurant->name])
                //->setFrom($fromEmail)//[$this->restaurant->restaurant_email => $this->restaurant->name]
                ->setTo($this->restaurant->restaurant_email)
                //->setReplyTo(\Yii::$app->params['supportEmail'])
                ->setSubject('Order #' . $this->order_uuid . ' from ' . $this->restaurant->name);
               // ->setReplyTo($replyTo)

            if(\Yii::$app->params['elasticMailIpPool'])
                $mailer->setHeader ("poolName", \Yii::$app->params['elasticMailIpPool']);

            try {
                $mailer->send();
            } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
                // Handle email transport-specific exceptions
                Yii::error( "Failed to send email: " . $e->getMessage());
            } catch (\Exception $e) {
                // Handle any other exceptions
                Yii::error( "An error occurred: " . $e->getMessage());
            }
        }
    }

    // /**
    //  * Update order status to pending
    //  */
    // public function restockItems()
    // {
    //     foreach ($this->getOrderItems()->all() as $orderItem) {
    //
    //           if($orderItem->item)
    //               continue;
    //
    //         $orderItemExtraOptions = $orderItem->getOrderItemExtraOptions();
    //
    //         if ($orderItemExtraOptions->count() > 0) {
    //             foreach ($orderItemExtraOptions->all() as $orderItemExtraOption) {
    //                 if ($orderItemExtraOption->order_item_extra_option_id && $orderItemExtraOption->order_item_extra_option_id && $orderItemExtraOption->extra_option_id)
    //                     $orderItemExtraOption->extraOption->increaseStockQty($orderItem->qty);
    //             }
    //         }
    //
    //         $orderItem->item->increaseStockQty($orderItem->qty);
    //
    //         self::updateAll(['items_has_been_restocked' => true], [
    //             'order_uuid' => $this->order_uuid
    //         ]);
    //     }
    //
    //
    // }

    /**
     * todo: Restock if order cancel
     */
    public function restockItems()
    {
        $orderItems = $this->getOrderItems();
        //$orderItemExtraOptions = $this->getOrderItemExtraOptions();

        foreach ($orderItems->all() as $orderItem)
        {
            //if custom item or not tracking

            if (!$orderItem->item || !$orderItem->item->track_quantity) {
                continue;
            }

            //for simple product

            if(!$orderItem->variant) {

                $orderItemExtraOptions = $orderItem->getOrderItemExtraOptions();

                foreach ($orderItemExtraOptions->all() as $orderItemExtraOption) {
                    if ($orderItemExtraOption->extraOption)
                        $orderItemExtraOption->extraOption->increaseStockQty($orderItem->qty);
                }
            }

            $orderItem->item->increaseStockQty($orderItem->qty);

            //if variant

            if ($orderItem->variant) {
                $orderItem->variant->increaseStockQty($orderItem->qty);
            }

            self::updateAll(['items_has_been_restocked' => true], [
                'order_uuid' => $this->order_uuid
            ]);
        }
    }

    /**
     * deduct stock
     * 1) on payment success
     * 2) on COD orders
     * 3) Free checkout
     */
    public function deductStock()
    {
        $orderItems = $this->getOrderItems()
            ->with(['item']);

        //$orderItemExtraOptions = $this->getOrderItemExtraOptions();

        foreach ($orderItems->all() as $orderItem)
        {
            //if custom item or not tracking

            if (!$orderItem->item || !$orderItem->item->track_quantity) {
                continue;
            }

            //for simple product

            if(!$orderItem->variant) {

                $orderItem->item->decreaseStockQty($orderItem->qty);

                $orderItemExtraOptions = $orderItem->getOrderItemExtraOptions();

                foreach ($orderItemExtraOptions->all() as $orderItemExtraOption) {
                    if ($orderItemExtraOption->extraOption)
                        $orderItemExtraOption->extraOption->decreaseStockQty($orderItem->qty);
                }
            } else {
                $orderItem->variant->decreaseStockQty($orderItem->qty);
            }
        }
    }

    /**
     * Update order status to pending
     */
    public function changeOrderStatusToPending()
    {
        $this->order_status = self::STATUS_PENDING;
        $this->save(false);

        $this->deductStock();

        $this->sendOrderNotification();

        $productsList = null;

        foreach ($this->orderItems as $orderedItem) {
            $productsList[] = [
                'product_id' => $orderedItem->item_uuid,
                'sku' => $orderedItem->item ? $orderedItem->item->sku : null,
                'name' => $orderedItem->item_name,
                'price' => $orderedItem->item_price,
                'quantity' => $orderedItem->qty,
                'url' => $this->restaurant->restaurant_domain . '/product/' . $orderedItem->item_uuid,
            ];
        }

        $plugn_fee = 0;
        $payment_gateway_fee = 0;
        $plugn_fee_kwd = 0;

        //$total_price = $this->total_price;
        //$delivery_fee = $this->delivery_fee;
        //$subtotal = $this->subtotal;
        //$currency = $this->currency_code;

        $kwdCurrency = Currency::findOne(['code' => 'KWD']);

        //using store currency instead of user as user can have any currency but totals will be in store currency

        $rateKWD = $kwdCurrency->rate / $this->restaurant->currency->rate;

        $rate = 1 / $this->restaurant->currency->rate;// to USD

        if ($this->payment_uuid) {

            $plugn_fee_kwd = ($this->payment->plugn_fee + $this->payment->partner_fee) * $rateKWD;

            $plugn_fee = ($this->payment->plugn_fee + $this->payment->partner_fee) * $rate;

            //$total_price = $total_price * $rate;
            //$delivery_fee = $delivery_fee * $rate;
            //$subtotal = $subtotal * $rate;
            $payment_gateway_fee = $this->payment->payment_gateway_fee * $rate;
        }
        // if no payment (COD orders)
        else {

            $invoice_item = InvoiceItem::find()
                ->andWhere(['order_uuid' => $this->order_uuid])
                ->one();

            if($invoice_item) {
                $plugn_fee_kwd = ($invoice_item->total) * $rateKWD;
                $plugn_fee = ($invoice_item->total) * $rate;
            }

            //$total_price = $total_price * $rate;
            //$delivery_fee = $delivery_fee * $rate;
            //$subtotal = $subtotal * $rate;
            $payment_gateway_fee = 0;
        }

        if($this->restaurant->sourceCampaign) {

            $this->restaurant->sourceCampaign->updateCounters([
                'no_of_orders' => 1,
                'total_commission' => $plugn_fee,
                "total_gateway_fee" => $payment_gateway_fee
            ]);
        }

            $order_total = $this->total_price * $rate;

            $store = $this->restaurant;

            $itemTypes = [];
            foreach ($store->restaurantItemTypes as $restaurantItemType) {
                $itemTypes[] = $restaurantItemType->businessItemType->business_item_type_en;
            }

            $data = [
                "restaurant_uuid" => $this->restaurant_uuid,
                "status" => $this->getOrderStatusInEnglish(),
                "store" => $store->name,
                "customer_name" => $this->customer_name,
                "customer_email" => $this->customer_email,
                "customer_phone_number" => $this->customer_phone_number,
                "customer_id" => $this->customer_id,
                "country" => $this->country_name,
                'checkout_id' => $this->order_uuid,
                'order_id' => $this->order_uuid,
                'is_market_order' => $this->is_market_order,
                'total' => $order_total,
                'revenue' => $plugn_fee,
                "store_revenue" => $order_total - $plugn_fee,
                'gateway_fee' => $payment_gateway_fee,
                'payment_method' => $this->payment_method_name,
                'gateway' => $this->payment_method_name,// $this->payment_uuid ? 'Tap' : null,
                'shipping' => ($this->delivery_fee * $rate),
                'subtotal' => ($this->subtotal * $rate),
                'currency' => $this->currency_code,
                "cash" => $this->paymentMethod && $this->paymentMethod->payment_method_code == PaymentMethod::CODE_CASH?
                    ($this->total_price * $rate): 0,
                'coupon' => $this->voucher && $this->voucher->code ? $this->voucher->code : null,
                'products' => $productsList ? $productsList : null,
                'storeItemTypes' => $itemTypes,
                "order_mode" => $this->order_mode,
                "area_name" => $this->area_name,
                "unit_type" => $this->unit_type,
                "house_no" => $this->house_number,
                "floor" => $this->floor,
                "address_line_1" => $this->address_1,
                "address_line_2" => $this->address_2,
                "building_no" => $this->house_number,
                "block" => $this->block,
                "street" => $this->street,
                "avenue" => $this->avenue,
                "postal_code" => $this->postalcode,
                "special_direction" => $this->special_directions,
                "expected_at" => $this->estimated_time_of_arrival,
                "store_order_count" => $this->restaurant->total_orders,
            ];

            if($store->restaurantType) {

                if ($store->restaurantType->merchantType) {
                    $data['merchant_type'] = $store->restaurantType->merchantType->merchant_type_en;
                }

                if ($store->restaurantType->businessType) {
                    $data['business_type'] = $store->restaurantType->businessType->business_type_en;
                }

                if ($store->restaurantType->businessCategory) {
                    $data['business_category'] = $store->restaurantType->businessCategory->business_category_en;
                }

                //for order in specific category

                if($store->restaurantType->businessCategory)
                    Yii::$app->eventManager->track('Order Placed in Category', $data,
                        null,
                        $store->restaurantType->businessCategory->business_category_en);

                //for order in specific business type

                if($store->restaurantType->businessType)
                    Yii::$app->eventManager->track('Order Placed in Business Type', $data,
                        null,
                        $store->restaurantType->businessType->business_type_en);

                //for order in specific merchant type

                if($store->restaurantType->merchantType)
                    Yii::$app->eventManager->track('Order Placed in Merchant Type', $data,
                        null,
                        $store->restaurantType->merchantType->merchant_type_en);
            }

            //for order tracking

            Yii::$app->eventManager->track('Order Placed', $data,
                null, 
                $this->restaurant_uuid);

            //todo: should able to run this in dev too
        if (!$this->is_sandbox) {
            Yii::$app->walletManager->addEntry([
                'amount' => $plugn_fee_kwd,
                'data' => 'Plugn: Commission for Order #'. $this->order_uuid,//$plugn_fee
                'tagNames' => 'Plugn Order Commission',
                'user_uuid' => Yii::$app->walletManager->companyWalletUserID
            ]);
        }

        //test commission in kwd / test commission in USD from 1) kwd store 2) SAR store

    }

    /**
     * Update order total price and items total price
     */
    public function updateOrderTotalPrice($attribute = 'delivery_zone_id')
    {
        if ($this->order_mode == static::ORDER_MODE_DELIVERY) {

            if (!$this->deliveryZone) {
                return $this->addError($attribute, Yii::t('app', 'Delivery zone is invalid'));
            }

            $this->delivery_fee = round($this->deliveryZone->delivery_fee, $this->currency->decimal_place);
        }

        if ($this->order_status != Order::STATUS_REFUNDED && $this->order_status != Order::STATUS_PARTIALLY_REFUNDED) {
            $this->subtotal_before_refund = round($this->calculateOrderItemsTotalPrice(), $this->currency->decimal_place);
            $this->total_price_before_refund = round($this->calculateOrderTotalPrice(), $this->currency->decimal_place);
        }

        $this->subtotal = round($this->calculateOrderItemsTotalPrice(), $this->currency->decimal_place);

        $this->total_price = round($this->calculateOrderTotalPrice(), $this->currency->decimal_place);

        $this->setScenario(self::SCENARIO_UPDATE_TOTAL);

        return $this->save();
    }

    /**
     * Calculate order item's total price
     */
    public function calculateOrderItemsTotalPrice()
    {
        $totalPrice = 0;

        foreach ($this->getOrderItems()->all() as $item) {

            if ($item) {
                $totalPrice += $item->calculateOrderItemPrice();

            }
        }
        return $totalPrice;
    }

    /**
     * Calculate order's total price
     */
    public function calculateOrderTotalPrice($totalPrice = null)
    {
        if(!$totalPrice)
            $totalPrice = $this->calculateOrderItemsTotalPrice();

        if ($totalPrice > 0)
        {
            if ($this->voucher)
            {
                $discountAmount = null;

                switch ($this->voucher->discount_type) {
                    case Voucher::DISCOUNT_TYPE_PERCENTAGE:
                        $discountAmount = $totalPrice * ($this->voucher->discount_amount / 100);
                        break;
                    case Voucher::DISCOUNT_TYPE_FREE_DELIVERY:
                        $discountAmount = $this->deliveryZone?
                            round($this->deliveryZone->delivery_fee, $this->currency->decimal_place): 0;
                        break;
                    default:
                        $discountAmount =  $this->voucher->discount_amount;
                }

                $totalPrice -= $discountAmount;

                $totalPrice = $this->voucher->discount_type == Voucher::DISCOUNT_TYPE_FREE_DELIVERY || $totalPrice > 0 ?
                    $totalPrice : 0;
            }
            else if ($this->bank_discount_id && $this->bankDiscount->minimum_order_amount <= $totalPrice)
            {
                $discountAmount = $this->bankDiscount->discount_type == BankDiscount::DISCOUNT_TYPE_PERCENTAGE ?
                    ($totalPrice * ($this->bankDiscount->discount_amount / 100)) :
                    $this->bankDiscount->discount_amount;

                $totalPrice -= $discountAmount;

                $totalPrice = $totalPrice > 0 ? $totalPrice : 0;
            }
        }

        /**
         *  &&
        (
        !$this->voucher ||
        ($this->voucher && $this->voucher->discount_type !== Voucher::DISCOUNT_TYPE_FREE_DELIVERY)
        )
         */

        if ($this->order_mode == static::ORDER_MODE_DELIVERY) {
            $totalPrice += round($this->deliveryZone->delivery_fee, $this->currency->decimal_place);
        }

        if ($this->delivery_zone_id)
        {
            if ($this->deliveryZone->delivery_zone_tax)
            {
                $this->tax = $totalPrice * ($this->deliveryZone->delivery_zone_tax / 100);
                $totalPrice += $this->tax;
            }
            else if ($this->deliveryZone->businessLocation->business_location_tax)
            {
                $this->tax = $totalPrice * ($this->deliveryZone->businessLocation->business_location_tax / 100);
                $totalPrice += $this->tax;
            }
        }
        else if (!$this->delivery_zone_id && $this->pickup_location_id && $this->pickupLocation->business_location_tax)
        {
            $this->tax = $totalPrice * ($this->pickupLocation->business_location_tax / 100);
            $totalPrice += $this->tax;
        }

        // new changes done as calculation was not saving while placing an order.

        $this->total_price = $totalPrice;

        return $totalPrice;
    }

    /**
     * @return bool
     * @throws \yii\db\StaleObjectException
     */
    public function beforeDelete()
    {
        if (!$this->items_has_been_restocked) {

            $orderItems = OrderItem::find()
                ->where(['order_uuid' => $this->order_uuid])
                ->all();

            foreach ($orderItems as $model)
                $model->delete();
        }

        return parent::beforeDelete();
    }

    /**
     * @param bool $insert
     * @return bool|void
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        if($insert)
        {
            if(Yii::$app->request instanceof \yii\web\Request) {

                $this->ip_address = $this->resolveClientIp();

                $count = self::find()
                    ->andWhere(['restaurant_uuid' => $this->restaurant_uuid])
                    ->andWhere(['ip_address' => $this->ip_address])
                    ->andWhere("DATE(order_created_at) = DATE('" . date('Y-m-d') . "')")
                    ->count();

                //allowing 11 order per day for customer

                if ($count > 100) {
                    Yii::error("too may order from same ip");

                    //block ip

                    $biModel = new BlockedIp();
                    $biModel->ip_address = $this->ip_address;
                    $biModel->note = "Too many customer signups from same ip";
                    $biModel->save(false);

                    return $this->addError('ip_address', "Too many order");
                }
            }

            $this->is_sandbox = $this->restaurant->is_sandbox;

            if($this->order_mode == Order::ORDER_MODE_DELIVERY) {//&& $this->delivery_zone_id

                $isExists = $this->restaurant->getDeliveryZones()
                    ->andWhere(['delivery_zone_id' => $this->delivery_zone_id])
                    ->exists();

                if (!$isExists)
                    return $this->addError('delivery_zone_id', Yii::t('app', 'Store not delivering to this area.'));
            }

            if($this->order_mode == Order::ORDER_MODE_PICK_UP) { // && $this->pickup_location_id

                $isExists = $this->restaurant->getPickupBusinessLocations()
                    ->andWhere(['business_location.business_location_id' => $this->pickup_location_id])
                    ->exists();

                if (!$isExists)
                    return $this->addError('pickup_location_id', Yii::t('app', 'Invalid pickup location.'));
            }
        }

        if (!$this->currency_code) {

            if (!$this->restaurant || !$this->restaurant->currency)
            {
                return $this->addError(
                    'currency_code',
                    Yii::t('yii', "{attribute} is invalid.", [
                        'attribute' => Yii::t('app', 'Currency code')
                    ])
                );
            }

            $this->currency_code = $this->restaurant->currency->code;
        }

        if ($this->voucher && !$this->voucher_discount) {

            $this->discount_type = $this->voucher->discount_type;

            switch ($this->voucher->discount_type) {
                case Voucher::DISCOUNT_TYPE_PERCENTAGE:
                    $this->voucher_discount = $this->subtotal * ($this->voucher->discount_amount / 100);
                    break;
                case Voucher::DISCOUNT_TYPE_FREE_DELIVERY:
                    $this->voucher_discount = $this->deliveryZone?
                        round($this->deliveryZone->delivery_fee, $this->currency->decimal_place): 0;
                    break;
                default:
                    $this->voucher_discount = $this->voucher->discount_amount;
            }

            $this->voucher_code = $this->voucher->code;
            $this->discount_type = $this->voucher->discount_type;
        }

        if ($this->bankDiscount && !$this->bank_discount) {
            $this->bank_discount = $this->bankDiscount->discount_type == 1 ?
                $this->subtotal * ($this->bankDiscount->discount_amount / 100) : $this->bankDiscount->discount_amount;
        }

        //currency rate from store currency to order currency

        if (!$this->currency_rate && $this->restaurant->currency) {
            $this->store_currency_code = $this->restaurant->currency->code;
            $this->currency_rate = $this->currency->rate / $this->restaurant->currency->rate;
        }

        if ($insert && $this->scenario == self::SCENARIO_CREATE_ORDER_BY_ADMIN) {
            $this->order_status = self::STATUS_DRAFT;
        }

        if ($this->scenario == self::SCENARIO_UPDATE_TOTAL) {

          if ($this->voucher && $this->voucher->minimum_order_amount !== 0 && $this->calculateOrderItemsTotalPrice() < $this->voucher->minimum_order_amount)
            return  $this->addError('voucher_id', "We can't apply this code until you reach the minimum order amount");

            if ($this->order_mode == static::ORDER_MODE_DELIVERY) {

                //set ETA value
                \Yii::$app->timeZone = 'Asia/Kuwait';

                if ($this->is_order_scheduled) {
                    if ($this->scheduled_time_start_from) {
                        $this->estimated_time_of_arrival = date("Y-m-d H:i:s", strtotime($this->scheduled_time_start_from));
                    }
                } else {
                    if ($this->delivery_zone_id) {
                        $this->estimated_time_of_arrival =
                            date(
                                "Y-m-d H:i:s",
                                strtotime(
                                    '+' . $this->deliveryZone->delivery_time . ' ' . $this->deliveryZone->timeUnit,
                                    Yii::$app->formatter->asTimestamp(
                                        ((!$insert && $this->order_created_at == 'NOW()') || $insert) ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime($this->order_created_at))
                                    )
                                )
                            );
                    }
                }

            } else {
                $this->estimated_time_of_arrival = ((!$insert && $this->order_created_at == 'NOW()') || $insert) ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime($this->order_created_at));
            }

            // if($this->orderItems){
            //   foreach ($this->orderItems as $key => $orderItem) {
            //
            //     if($orderItem->item_uuid && $orderItem->item->prep_time){
            //       $this->estimated_time_of_arrival = date("c", strtotime('+' . $orderItem->item->prep_time  . ' ' . $orderItem->item->timeUnit,  Yii::$app->formatter->asTimestamp(date('Y-m-d H:i:s', strtotime($this->estimated_time_of_arrival)))));
            //     }
            //   }
            // }

            if ($this->restaurant->version == 4) {
                if (!$this->is_order_scheduled && $this->orderItems) {

                    $maxPrepTime = 0;

                    foreach ($this->orderItems as $key => $orderItem) {

                        if ($orderItem->item_uuid && $orderItem->item->prep_time) {

                            if ($orderItem->item->prep_time_unit == Item::TIME_UNIT_MIN)
                                $prep_time = intval($orderItem->item->prep_time);
                            else if ($orderItem->item->prep_time_unit == Item::TIME_UNIT_HRS)
                                $prep_time = intval($orderItem->item->prep_time) * 60;
                            else if ($orderItem->item->prep_time_unit == Item::TIME_UNIT_DAY)
                                $prep_time = intval($orderItem->item->prep_time) * 24 * 60;

                            if ($prep_time >= $maxPrepTime)
                                $maxPrepTime = $prep_time;
                        }

                    }

                    $baseTime = $this->estimated_time_of_arrival? 
                        Yii::$app->formatter->asTimestamp($this->estimated_time_of_arrival): time();

                    $this->estimated_time_of_arrival = date("Y-m-d H:i:s", strtotime('+' . $maxPrepTime . ' min', $baseTime));
                }
            } else {
                if ($this->orderItems) {

                    $maxPrepTime = 0;

                    foreach ($this->orderItems as $key => $orderItem) {

                        if ($orderItem->item_uuid && $orderItem->item->prep_time) {

                            if ($orderItem->item->prep_time_unit == Item::TIME_UNIT_MIN)
                                $prep_time = intval($orderItem->item->prep_time);
                            else if ($orderItem->item->prep_time_unit == Item::TIME_UNIT_HRS)
                                $prep_time = intval($orderItem->item->prep_time) * 60;
                            else if ($orderItem->item->prep_time_unit == Item::TIME_UNIT_DAY)
                                $prep_time = intval($orderItem->item->prep_time) * 24 * 60;

                            if ($prep_time >= $maxPrepTime)
                                $maxPrepTime = $prep_time;
                        }

                    }

                    $baseTime = $this->estimated_time_of_arrival? 
                        Yii::$app->formatter->asTimestamp($this->estimated_time_of_arrival): time();
                        
                    $this->estimated_time_of_arrival = date(
                        "Y-m-d H:i:s",
                        strtotime(
                        '+' . $maxPrepTime . ' min', 
                        $baseTime
                        )
                    );
                }
            }

                      if($this->restaurant->version == 4) {

                          $start_date = $this->estimated_time_of_arrival? 
                            date("Y-m-d H:i:s", mktime(00, 00, 0, date("m",strtotime($this->estimated_time_of_arrival)),  date("d",strtotime($this->estimated_time_of_arrival))  )): 
                            date("Y-m-d H:i:s", mktime(00, 00, 0, date("m"),  date("d")  ));

                          $end_date = $this->estimated_time_of_arrival? 
                            date("Y-m-d H:i:s", mktime(23, 59, 59, date("m",strtotime($this->estimated_time_of_arrival)),  date("d",strtotime($this->estimated_time_of_arrival)) )): 
                            date("Y-m-d H:i:s", mktime(23, 59, 59, date("m"),  date("d")));

                          $numOfPickupOrders = 0;
                          $numOfDeliveryOrders =  0;
                          $numOfOrders =  0;

                          if($this->order_mode  == static::ORDER_MODE_DELIVERY && $this->businessLocation->max_num_orders !== null){
                            $numOfPickupOrders = $this->businessLocation->getPickupOrders()->activeOrders()
                                    ->andWhere(['between', 'estimated_time_of_arrival', $start_date, $end_date])
                                    ->count();

                            $numOfDeliveryOrders = $this->businessLocation->getDeliveryOrders()->activeOrders()
                                    ->andWhere(['between', 'estimated_time_of_arrival', $start_date, $end_date])
                                    ->count();

                            $numOfOrders = $numOfPickupOrders + $numOfDeliveryOrders;

                            if($numOfOrders >= $this->businessLocation->max_num_orders){
                              return $this->addError(
                                  'max_order_limit',
                                  Yii::t('yii', "{attribute} is invalid.", [
                                    'attribute' => Yii::t('app', 'Sorry, order limit has been exceeded.')
                                  ])
                              );
                            }

                          } else if($this->order_mode  == static::ORDER_MODE_PICK_UP && $this->pickupLocation->max_num_orders !== null){


                            $numOfPickupOrders = $this->pickupLocation->getPickupOrders()->activeOrders()
                                    ->andWhere(['between', 'estimated_time_of_arrival', $start_date, $end_date])
                                    ->count();

                            $numOfDeliveryOrders = $this->pickupLocation->getDeliveryOrders()->activeOrders()
                                    ->andWhere(['between', 'estimated_time_of_arrival', $start_date, $end_date])
                                    ->count();


                            $numOfOrders = $numOfPickupOrders + $numOfDeliveryOrders;

                            if($numOfOrders >= $this->pickupLocation->max_num_orders){

                              return $this->addError(
                                'max_order_limit',
                                  Yii::t('yii', "{attribute} is invalid.", [
                                    'attribute' => Yii::t('app', 'Sorry, order limit has been exceeded.')
                                  ])
                              );
                            }
                          }

                        }

          }

        return true;
    }

    private function resolveClientIp()
    {
        $ip = trim((string) Yii::$app->request->getRemoteIP());

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $IParray = array_values(array_filter(array_map('trim', explode(',', $forwardedFor))));

            if (!empty($IParray) && filter_var($IParray[0], FILTER_VALIDATE_IP) !== false) {
                $ip = $IParray[0];
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }

    /**
     * @param bool $insert
     * @param array $changedAttributes
     */
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert) {

            if (is_null($this->items_has_been_restocked)) {
                $this->items_has_been_restocked = false;
            }

            if ($this->order_mode == static::ORDER_MODE_DELIVERY) {
                $this->delivery_time = $this->deliveryZone->delivery_time;
                //$this->save(false);
            }

            $this->customer_phone_number = str_replace(' ', '', $this->customer_phone_number);

            //Save Customer data

            $customer = Customer::find()->where([
                'customer_phone_number' => $this->customer_phone_number,
                'restaurant_uuid' => $this->restaurant_uuid
            ])->one();

            if (!$customer) {//new customer
                $customer = new Customer();
                $customer->restaurant_uuid = $this->restaurant_uuid;
                $customer->customer_name = $this->customer_name;
                $customer->country_code = $this->customer_phone_country_code;
                $customer->customer_phone_number = $this->customer_phone_number;

                if ($this->restaurant_uuid == 'rest_fe5b6a72-18a7-11ec-973b-069e9504599a' && $this->civil_id && $this->section && $this->class) {
                    $customer->civil_id = $this->civil_id;
                    $customer->section = $this->section;
                    $customer->class = $this->class;
                }
            } else {
                $customer->customer_name = $this->customer_name;

                if ($this->restaurant_uuid == 'rest_fe5b6a72-18a7-11ec-973b-069e9504599a' && $this->civil_id && $this->section && $this->class) {
                    $customer->civil_id = $this->civil_id;
                    $customer->section = $this->section;
                    $customer->class = $this->class;
                }
            }

            if ($this->customer_email != null)
                $customer->customer_email = $this->customer_email;

            $customer->save(false);

            $this->customer_id = $customer->customer_id;

            if ($this->voucher_id) {

                $voucher_model = Voucher::findOne($this->voucher_id);

                if ($voucher_model->isValid($this->customer_phone_number)) {
                    $customerVoucher = new CustomerVoucher();
                    $customerVoucher->customer_id = $this->customer_id;
                    $customerVoucher->voucher_id = $this->voucher_id;
                    $customerVoucher->save();
                }
            }

            if ($this->order_mode == static::ORDER_MODE_DELIVERY) {

                if ($this->area_id) {
                    $area_model = Area::findOne($this->area_id);
                    $this->area_name = $area_model->area_name;
                    $this->area_name_ar = $area_model->area_name_ar;
                }
            }

            $payment_method_model = PaymentMethod::findOne($this->payment_method_id);

            if ($this->payment_method_id && !$payment_method_model) {
                throw new BadRequestHttpException('payment gateway not found');
            }

            if ($payment_method_model) {
                $this->payment_method_name = $payment_method_model->payment_method_name;
                $this->payment_method_name_ar = $payment_method_model->payment_method_name_ar;
            }

            if (!$this->currency_code && $this->restaurant && $this->restaurant->currency) {
                $this->currency_code = $this->restaurant->currency->code;
            }

            $this->store_currency_code = $this->restaurant->currency?
                $this->restaurant->currency->code: "KWD";

            //$this->save(false);
        }
        //on update
        else {

            if ($this->payment_method_id && !$this->payment_method_name) {

                $payment_method_model = PaymentMethod::findOne($this->payment_method_id);

                if(!$payment_method_model)
                    throw new BadRequestHttpException('payment gateway not found');

                $this->payment_method_name = $payment_method_model->payment_method_name;
                $this->payment_method_name_ar = $payment_method_model->payment_method_name_ar;
            }

            if (isset($changedAttributes['voucher_id']) && $changedAttributes['voucher_id'] != $this->voucher_id) {

                $voucher_model = Voucher::findOne($this->voucher_id);

                if ($voucher_model->isValid($this->customer_phone_number)) {
                    $customerVoucher = new CustomerVoucher();
                    $customerVoucher->customer_id = $this->customer_id;
                    $customerVoucher->voucher_id = $this->voucher_id;
                    $customerVoucher->save();
                }
            }
        }

        if (
            $this->scenario == self::SCENARIO_UPDATE_STATUS &&
            isset($changedAttributes['order_status']) && $this->order_status == self::STATUS_COMPLETE
        ) {

                $shipping_partner = "";

                if($this->armada_tracking_link) {
                    $shipping_partner = "Armada";
                } else if($this->aramex_shipment_id) {
                    $shipping_partner = "Aramex";
                } else if($this->mashkor_order_status) {
                    $shipping_partner = "Mashkor";
                }

                Yii::$app->eventManager->track('Order Delivered', [
                    "order_uuid" => $this->order_uuid,
                    "shipping_partner" => $shipping_partner
                ],
                    null,
                    $this->restaurant_uuid
                );
        }

        /**
         * when order status get update from non cancelled to canelled
         */
        if (
            $this->scenario == self::SCENARIO_UPDATE_STATUS &&
            $this->items_has_been_restocked == false &&
            isset($changedAttributes['order_status']) && $changedAttributes['order_status'] != self::STATUS_CANCELED && $this->order_status == self::STATUS_CANCELED
        ) {
            $this->restockItems();

            //cancel armada driver

            $armadaApiKey = $this->restaurant->armada_api_key;

            if($this->businessLocation)
                $armadaApiKey = $this->businessLocation->armada_api_key;

            if($this->armada_tracking_link)
                Yii::$app->armadaDelivery->cancelDelivery($this, $armadaApiKey);

            /**todo: check aramda order status?
            $this->armada_order_status
            pending: the order has been received and is waiting to be dispatched to a driver
dispatched: the order has been dispatched to a driver who is on his way to the merchant
en route: the order has been picked up from the merchant and is being delivered to the customer
complete: the order has been successfully delivered
canceled: the order has been canceled from the merchant
failed: the order has failed to find a driver */

                $shipping_partner = "";

                if($this->armada_tracking_link) {
                    $shipping_partner = "Armada";
                } else if($this->aramex_shipment_id) {
                    $shipping_partner = "Aramex";
                } else if($this->mashkor_order_status) {
                    $shipping_partner = "Mashkor";
                }

                Yii::$app->eventManager->track('Order Cancelled', [
                    "order_uuid" => $this->order_uuid,
                    "shipping_partner" => $shipping_partner
                ],
                    null,
                    $this->restaurant_uuid
                );
        }

        //Send SMS To customer

        if ($this->customer_phone_country_code == 965 && !$insert &&
            $this->restaurant_uuid != 'rest_7351b2ff-c73d-11ea-808a-0673128d0c9c' &&
            !$this->sms_sent &&
            isset($changedAttributes['order_status']) &&
            $changedAttributes['order_status'] == self::STATUS_PENDING && $this->order_status == self::STATUS_ACCEPTED
        ) {

            try {

                $response = Yii::$app->smsComponent->sendSms($this->customer_phone_number, $this->order_uuid);

                if (!$response->isOk)
                    Yii::error('Error while Sending SMS' . json_encode($response->data));
                else {
                    $this->sms_sent = 1;
                    //$this->save(false);
                }

                if (!$response->isOk)
                    Yii::error('Error while Sending SMS' . json_encode($response->data));
                else {
                    $this->sms_sent = 1;
                    //$this->save(false);
                }
            } catch (\Exception $err) {
                //todo: show notification to customer to update number?
                Yii::error('Error while Sending SMS.' . json_encode($err));
            }
        }

        //Update delivery area

        if (
            (
                !$insert && $this->order_mode == static::ORDER_MODE_DELIVERY &&
                isset($changedAttributes['area_id']) && $changedAttributes['area_id'] != $this->getOldAttribute('area_id') && $this->area_id
            ) ||
            (
                $insert && $this->order_mode == static::ORDER_MODE_DELIVERY && $this->area_id
            )
        ) {
            $area_model = Area::findOne($this->area_id);
            $this->area_name = $area_model->area_name;
            $this->area_name_ar = $area_model->area_name_ar;



            //$this->save(false);
        }

        if (
            (
                !$insert &&
                (isset($changedAttributes['area_id']) && $changedAttributes['area_id'] != $this->area_id) ||
                (isset($changedAttributes['pickup_location_id']) && $changedAttributes['pickup_location_id'] != $this->pickup_location_id)
            ) || $insert
        ) {

            if ($this->order_mode == static::ORDER_MODE_DELIVERY) {

                if ($this->delivery_zone_id) {

                    $deliveryZone = DeliveryZone::find()->where([
                      'delivery_zone_id' =>$this->delivery_zone_id,
                      'restaurant_uuid' =>$this->restaurant_uuid,
                    ])->one();

                    if ($deliveryZone)
                    {
                        $this->country_name = $deliveryZone->country->country_name;
                        $this->country_name_ar = $deliveryZone->country->country_name_ar;
                        $this->shipping_country_id = $deliveryZone->country->country_id;

                        if ($deliveryZone->business_location_id)
                            $this->business_location_name = $deliveryZone->businessLocation->business_location_name;

                        //$this->save(false);
                    }
                    else
                    {
                      return $this->addError(
                          'delivery_zone_id',
                          Yii::t('yii', "{attribute} is invalid.", [
                            'attribute' => Yii::t('app', "Store does not deliver to this delivery zone.")
                          ])
                      );
                    }
                }

            } else if ($this->order_mode == Order::ORDER_MODE_PICK_UP) {

                if ($this->pickup_location_id) {

                    $pickupLocation = BusinessLocation::find()->where([
                      'business_location_id' =>$this->pickup_location_id,
                      'restaurant_uuid' =>$this->restaurant_uuid,
                    ])->one();

                    if ($pickupLocation) {

                        $this->country_name = $pickupLocation->country->country_name;
                        $this->country_name_ar = $pickupLocation->country->country_name_ar;
                        $this->business_location_name = $pickupLocation->business_location_name;

                        //$this->save(false);
                    } else {

                      return $this->addError(
                          'pickup_location_id',
                          Yii::t('yii', "{attribute} is invalid.", [
                            'attribute' => Yii::t('app', "Pickup location doesn't exist")
                          ])
                      );
                    }
                }
            }
        }

        /* nonsense code
        if (!$insert && $this->customer_id) {

            //Save Customer data
            $customer = Customer::findOne($this->customer_id);

            if($customer) {
                $customer->customer_name = $this->customer_name;

                if ($this->customer_email != null)
                    $customer->customer_email = $this->customer_email;

                $customer->save(false);
            }
        }*/

        //todo : notification based on order status

        //if (isset($changedAttributes['order_status']) && $this->order_status != self::STATUS_PENDING)

        self::updateAll([
            'sms_sent' => $this->sms_sent,
            'customer_phone_number' => $this->customer_phone_number,
            'customer_id' => $this->customer_id,
            'area_name' => $this->area_name,
            'area_name_ar' => $this->area_name_ar,
            'country_name' => $this->country_name,
            'country_name_ar' => $this->country_name_ar,
            'business_location_name' => $this->business_location_name,
            'items_has_been_restocked' => $this->items_has_been_restocked,
            'delivery_time' => $this->delivery_time,
            'payment_method_name' => $this->payment_method_name,
            'payment_method_name_ar' => $this->payment_method_name_ar,
            'currency_code' => $this->currency_code
        ], [
            'order_uuid' => $this->order_uuid
        ]);
    }

    /**
     * @return array|array[]
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();

        $scenarios['updateStatus'] = ['order_status'];

        $scenarios[self::SCENARIO_APPLY_VOUCHER] = ["voucher_id", "discount_type", "voucher_discount", "subtotal_before_refund",
            "total_price_before_refund", "subtotal", "total_price"];

        $scenarios[self::SCENARIO_DELETE] = ['is_deleted'];

        $scenarios[self::SCENARIO_UPDATE_TOTAL] = [
            'delivery_fee',
            'subtotal_before_refund',
            'total_price_before_refund',
            'subtotal',
            'total_price'
        ];

        $scenarios[self::SCENARIO_UPDATE_ARMADA] = [
            'armada_tracking_link',
            'armada_qr_code_link',
            'armada_delivery_code'
        ];

        $scenarios[self::SCENARIO_UPDATE_ARMADA_STATUS] = [
            'armada_order_status',
            'order_status'
        ];

        $scenarios[self::SCENARIO_UPDATE_MASHKOR_STATUS] = [
            'mashkor_driver_name',
            'mashkor_driver_phone',
            'mashkor_tracking_link',
            'mashkor_order_status',
            'order_status'
        ];

        $scenarios[self::SCENARIO_UPDATE_MASHKOR] = [
            'mashkor_order_number',
            'mashkor_order_status'
        ];

        return $scenarios;
    }

    /**
     * mobile notification on order marked as paid
     */
    public function sendOrderNotification()
    {
        $itemNames = ArrayHelper::getColumn($this->orderItems, 'item_name');

        $heading = "New order received";

        $subtitle = '';

        if ($this->restaurant->name) {
            $content = "@ " . $this->restaurant->name;
        } else {
            $content = "@ " . $this->restaurant->name_ar;
        }

        //$content = "For " . implode (", ", $itemNames);

        /*Yii::t('app', "{currency} {amount}", [
            "amount" => number_format($this->total_price, 3),
            "currency" => $this->currency->code
        ]);*/

        foreach ($this->restaurant->agentAssignments as $agentAssignment) {

            if ($agentAssignment->role == AgentAssignment::AGENT_ROLE_BRANCH_MANAGER) {

                if ($this->order_mode == Order::ORDER_MODE_DELIVERY) {
                    if ($this->delivery_zone_id && $this->businessLocation && $this->businessLocation->business_location_id != $agentAssignment->business_location_id)
                        continue;
                } else {
                    if ($this->pickup_location_id && $this->pickup_location_id != $agentAssignment->business_location_id)
                        continue;
                }
            }

            $filters = [
                [
                    "field" => "tag",
                    "key" => "agent_id",
                    "relation" => "=",
                    "value" => $agentAssignment->agent_id
                ]
            ];

            $data = [
                'subject' => 'order_uuid',
                'transfer_id' => $this->order_uuid
            ];

            MobileNotification::notifyAgent($heading, $data, $filters, $subtitle, $content);
        }
    }

    public static function getTotalRevenueByWeek()
    {
        $cacheDuration = 60 * 60 * 24 * 365;// 365 day then delete from cache

        $cacheDependency = Yii::createObject([
            'class' => 'yii\caching\DbDependency',
            'reusable' => true,
            'sql' => 'SELECT COUNT(*) FROM `order`',
        ]);

        $revenue_generated_chart_data = [];

        $date_start = strtotime ('-6 days');//date('w')

        for ($i = 0; $i < 7; $i++) {
            $date = date ('Y-m-d', $date_start + ($i * 86400));

            $revenue_generated_chart_data[date ('w', strtotime ($date))] = array(
                'day' => date ('D', strtotime ($date)),
                'total' => 0
            );
        }

        $rows = Order::getDb()->cache(function($db) {

            return Order::find()
                ->activeOrders ()
                ->select (new Expression('order.order_created_at, SUM(`total_price`) as total'))
                ->andWhere (new Expression("DATE(order.order_created_at) >= DATE(NOW() - INTERVAL 6 DAY)"))
                ->groupBy (new Expression('DAY(order.order_created_at)'))
                ->asArray ()
                ->all ();

        }, $cacheDuration, $cacheDependency);

        foreach ($rows as $result) {
            $revenue_generated_chart_data[date ('w', strtotime ($result['order_created_at']))] = array(
                'day' => date ('D', strtotime ($result['order_created_at'])),
                'total' => (float) $result['total']
            );
        }

        $number_of_all_revenue_generated = Order::getDb()->cache(function($db) {

            return Order::find()
                ->activeOrders()
                ->andWhere(new Expression("DATE(order.order_created_at) >= DATE(NOW() - INTERVAL 6 DAY)"))
                ->sum('total_price');

        }, $cacheDuration, $cacheDependency);

        return [
            'revenue_generated_chart_data' => array_values($revenue_generated_chart_data),
            'number_of_all_revenue_generated' => (int) $number_of_all_revenue_generated
        ];
    }

    public static function getTotalOrdersByWeek()
    {
        $cacheDuration = 60 * 60 * 24 * 365;// 365 day then delete from cache

        $cacheDependency = Yii::createObject([
            'class' => 'yii\caching\DbDependency',
            'reusable' => true,
            'sql' => 'SELECT COUNT(*) FROM `order`',
        ]);

        $orders_received_chart_data = [];

        $date_start = strtotime ('-6 days');//date('w')

        for ($i = 0; $i < 7; $i++) {
            $date = date ('Y-m-d', $date_start + ($i * 86400));

            $orders_received_chart_data[date ('w', strtotime ($date))] = array(
                'day' => date ('D', strtotime ($date)),
                'total' => 0
            );
        }

        $rows = Order::getDb()->cache(function($db) {

            return Order::find()
                ->activeOrders ()
                ->select (new Expression('order_created_at, COUNT(*) as total'))
                ->andWhere (new Expression("DATE(order.order_created_at) >= DATE(NOW() - INTERVAL 6 DAY)"))
                ->groupBy (new Expression('DAY(order.order_created_at)'))
                ->asArray ()
                ->all ();

        }, $cacheDuration, $cacheDependency);

        foreach ($rows as $result) {
            $orders_received_chart_data[date ('w', strtotime ($result['order_created_at']))] = array(
                'day' => date ('D', strtotime ($result['order_created_at'])),
                'total' => (int) $result['total']
            );
        }

        $number_of_all_orders_received = Order::getDb()->cache(function($db) {

            return Order::find()
                ->activeOrders()
                ->andWhere(new Expression("DATE(order.order_created_at) >= DATE(NOW() - INTERVAL 6 DAY)"))
                ->count();

        }, $cacheDuration, $cacheDependency);

        return [
            'orders_received_chart_data' => array_values ($orders_received_chart_data),
            'number_of_all_orders_received' => (int) $number_of_all_orders_received
        ];
    }

    public static function getTotalOrdersByMonth()
    {
        $cacheDuration = 60 * 60 * 24 * 365;// 365 day then delete from cache

        $cacheDependency = Yii::createObject([
            'class' => 'yii\caching\DbDependency',
            'reusable' => true,
            'sql' => 'SELECT COUNT(*) FROM `order`',
        ]);

        $orders_received_chart_data = [];

        $date_start = date('Y') . '-' . date('m', strtotime('-1 month')) . '-1';

        for ($i = 1; $i <= date('t', strtotime($date_start)); $i++) {
            $orders_received_chart_data[$i] = array(
                'day'   => $i,
                'total' => 0
            );
        }

        $rows = Order::getDb()->cache(function($db) {

            return Order::find()
                ->activeOrders ()
                ->select (new Expression('order_created_at, COUNT(*) as total'))
                ->andWhere(new Expression("DATE(order.order_created_at) >= (NOW() - INTERVAL 1 MONTH)"))
                ->groupBy (new Expression('DAY(order.order_created_at)'))
                ->asArray ()
                ->all ();

        }, $cacheDuration, $cacheDependency);

        foreach ($rows as $result) {
            $orders_received_chart_data[date ('j', strtotime ($result['order_created_at']))] = array(
                'day' => (int) date ('j', strtotime ($result['order_created_at'])),
                'total' => (int) $result['total']
            );
        }

        $number_of_all_orders_received = Order::getDb()->cache(function($db) {

            return Order::find()
                ->activeOrders()
                ->andWhere(new Expression("DATE(order.order_created_at) >= (NOW() - INTERVAL 1 MONTH)"))
                ->count();

        }, $cacheDuration, $cacheDependency);

        return [
            'orders_received_chart_data' => array_values ($orders_received_chart_data),
            'number_of_all_orders_received' => (int) $number_of_all_orders_received
        ];
    }

    public static function getTotalOrdersByMonths($months)
    {
        $cacheDuration = 60 * 60 * 24 * 365;// 365 day then delete from cache

        $cacheDependency = Yii::createObject([
            'class' => 'yii\caching\DbDependency',
            'reusable' => true,
            'sql' => 'SELECT COUNT(*) FROM `order`',
        ]);

        $orders_received_chart_data = [];

        $date_start = date('Y') . '-' . date('m', strtotime('-'.$months.' month')) . '-1';
        $date_end = date('Y-m-d', strtotime('last day of previous month'));
        //date('Y') . '-' . date('m') . '-1';

        for ($i = 0; $i <= $months; $i++) {

            $month = date('m', strtotime('-'.($months - $i).' month'));

            $orders_received_chart_data[$month] = array(
                'month'   => date('F', strtotime('-'.($months - $i).' month')),
                'total' => 0
            );
        }

        $rows = Order::getDb()->cache(function($db) use($months) {

            return Order::find()
                ->activeOrders ()
                ->select (new Expression('order_created_at, COUNT(*) as total'))
                ->andWhere(new Expression("DATE(order.order_created_at) >= (NOW() - INTERVAL ".$months." MONTH)"))
                ->groupBy (new Expression('MONTH(order.order_created_at)'))
                ->asArray ()
                ->all ();

        }, $cacheDuration, $cacheDependency);

        foreach ($rows as $result) {
            $orders_received_chart_data[date ('m', strtotime ($result['order_created_at']))] = array(
                'month' => Yii::t('app', date ('F', strtotime ($result['order_created_at']))),
                'total' => (int) $result['total']
            );
        }

        $number_of_all_orders_received = Order::getDb()->cache(function($db) use($months) {

            return Order::find()
                ->activeOrders()
                ->andWhere(new Expression("DATE(order.order_created_at) >= (NOW() - INTERVAL ".$months." MONTH)"))
                ->count();

        }, $cacheDuration, $cacheDependency);

        return [
            'orders_received_chart_data' => array_values ($orders_received_chart_data),
            'number_of_all_orders_received' => (int) $number_of_all_orders_received
        ];
    }

    public static function getTotalOrdersByInterval($interval) {
        switch ($interval) {
            case "last-month":
                return self::getTotalOrdersByMonth();
            case "week":
                return self::getTotalOrdersByWeek();
            default:
                return self::getTotalOrdersByMonths(str_replace(["last-", "-months"], ["", ""], $interval));
        }
    }

    public static function getTotalRevenueByMonth()
    {
        $cacheDuration = 60 * 60 * 24 * 365;// 365 day then delete from cache

        $cacheDependency = Yii::createObject([
            'class' => 'yii\caching\DbDependency',
            'reusable' => true,
            'sql' => 'SELECT COUNT(*) FROM `order`',
        ]);

        $revenue_generated_chart_data = [];

        $date_start = date('Y') . '-' . date('m', strtotime('-1 month')) . '-1';

        for ($i = 1; $i <= date('t', strtotime($date_start)); $i++) {
            $revenue_generated_chart_data[$i] = array(
                'day'   => $i,
                'total' => 0
            );
        }

        $rows = Order::getDb()->cache(function($db) {

            return Order::find()
                ->activeOrders ()
                ->select (new Expression('order.order_created_at, SUM(`total_price`) as total'))
                ->andWhere(new Expression("DATE(order.order_created_at) >= (NOW() - INTERVAL 1 MONTH)"))
                ->groupBy (new Expression('DAY(order.order_created_at)'))
                ->asArray ()
                ->all ();

        }, $cacheDuration, $cacheDependency);

        foreach ($rows as $result) {
            $revenue_generated_chart_data[date ('j', strtotime ($result['order_created_at']))] = array(
                'day' => (int) date ('j', strtotime ($result['order_created_at'])),
                'total' => (float) $result['total']
            );
        }

        $number_of_all_revenue_generated = Order::getDb()->cache(function($db) {

            return Order::find()
                ->activeOrders()
                ->andWhere(new Expression("DATE(order.order_created_at) >= (NOW() - INTERVAL 1 MONTH)"))
                ->sum('total_price');

        }, $cacheDuration, $cacheDependency);

        return [
            'revenue_generated_chart_data' => array_values($revenue_generated_chart_data),
            'number_of_all_revenue_generated' => (int) $number_of_all_revenue_generated
        ];
    }

    public static function getTotalRevenueByMonths($months)
    {
        $cacheDuration = 60 * 60 * 24 * 365;// 365 day then delete from cache

        $cacheDependency = Yii::createObject([
            'class' => 'yii\caching\DbDependency',
            'reusable' => true,
            'sql' => 'SELECT COUNT(*) FROM `order`',
        ]);

        $revenue_generated_chart_data = [];

        $date_start = date('Y') . '-' . date('m', strtotime('-'.$months.' month')) . '-1';
        $date_end = date('Y-m-d', strtotime('last day of previous month'));
        //date('Y-m-d');//date('Y') . '-' . date('m') . '-1';

        for ($i = 0; $i <= $months; $i++) {

            $month = date('m', strtotime('-'.($months - $i).' month'));

            $revenue_generated_chart_data[$month] = array(
                'month'   => date('F', strtotime('-'.($months - $i).' month')),
                'total' => 0
            );
        }

        $rows = Order::getDb()->cache(function($db) use($months) {

            return Order::find()
                ->activeOrders ()
                ->select (new Expression('order.order_created_at, SUM(`total_price`) as total'))
                ->andWhere(new Expression("DATE(order.order_created_at) >= (NOW() - INTERVAL ".$months." MONTH)"))
                ->groupBy (new Expression('MONTH(order.order_created_at)'))
                ->asArray ()
                ->all ();

        }, $cacheDuration, $cacheDependency);

        foreach ($rows as $result) {
            $revenue_generated_chart_data[date ('m', strtotime ($result['order_created_at']))] = array(
                'month' => Yii::t('app', date ('F', strtotime ($result['order_created_at']))),
                'total' => (float) $result['total']
            );
        }

        $number_of_all_revenue_generated = Order::getDb()->cache(function($db) use($months) {

            return Order::find()
                ->activeOrders()
                ->andWhere(new Expression("DATE(order.order_created_at) >= (NOW() - INTERVAL ".$months." MONTH)"))
                ->sum('total_price');

        }, $cacheDuration, $cacheDependency);

        return [
            'revenue_generated_chart_data' => array_values($revenue_generated_chart_data),
            'number_of_all_revenue_generated' => (int) $number_of_all_revenue_generated
        ];
    }

    /**
     * get order total converted to selected currency
     * @return float
     */
    public function getTotal() {
        return round($this->total_price * $this->currency_rate, $this->currency->decimal_place);
    }

    /**
     * @return query\OrderQuery
     */
    public static function find()
    {
        return new query\OrderQuery(get_called_class());
    }

    /**
     * Gets query for [[Country]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCountry($modelClass = "\common\models\Country")
    {
        return $this->hasOne($modelClass::className(), ['country_id' => 'shipping_country_id']);
    }

    /**
     * Gets query for [[DeliveryZone]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getDeliveryZone($modelClass = "\common\models\DeliveryZone")
    {
        return $this->hasOne($modelClass::className(), ['delivery_zone_id' => 'delivery_zone_id']);
            //->andWhere(['delivery_zone.is_deleted' => 0]);
    }

    /**
     * Gets query for [[BankDiscount]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBankDiscount($modelClass = "\common\models\BankDiscount")
    {
        return $this->hasOne($modelClass::className(), ['bank_discount_id' => 'bank_discount_id']);
    }

    /**
     * Gets query for [[Restaurant]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRestaurant($modelClass = "\common\models\Restaurant")
    {
        return $this->hasOne($modelClass::className(), ['restaurant_uuid' => 'restaurant_uuid']);
    }

    /**
     * @param $modelClass
     * @return \yii\db\ActiveQuery
     */
    public function getRestaurantType($modelClass = "\common\models\RestaurantType")
    {
        return $this->hasOne($modelClass::className(), ['restaurant_uuid' => 'restaurant_uuid']);
    }

    /**
     * Gets query for [[Currency]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCurrency($modelClass = "\common\models\Currency")
    {
        return $this->hasOne($modelClass::className(), ['code' => 'currency_code']);
    }

    /**
     * Gets query for [[RestaurantDelivery]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRestaurantDelivery($modelClass = "\common\models\RestaurantDelivery")
    {
        return $this->hasOne($modelClass::className(), ['area_id' => 'area_id'])
            ->via('area')
            ->andWhere(['restaurant_uuid' => $this->restaurant_uuid]);
    }

    /**
     * Gets query for [[State]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getState($modelClass = "\common\models\State")
    {
        return $this->hasOne($modelClass::className(), ['state_id' => 'state_id']);
    }

    /**
     * Gets query for [[Area]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getArea($modelClass = "\common\models\Area")
    {
        return $this->hasOne($modelClass::className(), ['area_id' => 'area_id']);
    }

    /**
     * Gets query for [[PaymentMethod]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPaymentMethod($modelClass = "\common\models\PaymentMethod")
    {
        return $this->hasOne($modelClass::className(), ['payment_method_id' => 'payment_method_id']);
    }

    /**
     * Gets query for [[OrderItemExtraOption]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getOrderItemExtraOptions($modelClass = "\common\models\OrderItemExtraOption")
    {
        return $this->hasMany($modelClass::className(), ['order_item_id' => 'order_item_id'])
            ->via('orderItems');
    }

    /**
     * Gets query for [[OrderItems]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getOrderItems($modelClass = "\common\models\OrderItem")
    {
        return $this->hasMany($modelClass::className(), ['order_uuid' => 'order_uuid'])
            ->with('orderItemExtraOptions');
    }

    /**
     * Gets query for [[OrderItems]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getSelectedItems($modelClass = "\common\models\OrderItem")
    {
        return $this->hasMany($modelClass::className(), ['order_uuid' => 'order_uuid']);
    }

    /**
     * Gets query for [[Items]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getItems($modelClass = "\common\models\Item")
    {
        return $this->hasMany($modelClass::className(), ['item_uuid' => 'item_uuid'])
            ->via('orderItems');
    }


    public function getItemImage($modelClass = "\agent\models\ItemImage")
    {
        return $this->hasOne($modelClass::className(), ['item_uuid' => 'item_uuid'])
            ->via('orderItems');
    }

    /**
     * Gets query for [[Customer]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getCustomer($modelClass = "\common\models\Customer")
    {
        return $this->hasOne($modelClass::className(), ['customer_id' => 'customer_id']);
    }

    /**
     * Gets query for [[RestaurantBranch]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRestaurantBranch($modelClass = "\common\models\RestaurantBranch")
    {
        return $this->hasOne($modelClass::className(), ['restaurant_branch_id' => 'restaurant_branch_id']);
    }

    /**
     * restaurant invoice item for order commission
     * @param $modelClass
     * @return \yii\db\ActiveQuery
     */
    public function getInvoiceItem($modelClass = "\common\models\InvoiceItem")
    {
        return $this->hasOne($modelClass::className(), ['order_uuid' => 'order_uuid']);
    }

    /**
     * Gets query for [[PaymentUu]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPayment($modelClass = "\common\models\Payment")
    {
        return $this->hasOne($modelClass::className(), ['payment_uuid' => 'payment_uuid'])
            ->orderBy('payment_created_at DESC');
    }

    /**
     * Gets query for [[Payments]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPayments($modelClass = "\common\models\Payment")
    {
        return $this->hasMany($modelClass::className(), ['payment_uuid' => 'payment_uuid'])
            ->orderBy('payment_created_at DESC');
    }

    /**
     * Gets query for [[RefundedTotal]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRefundedTotal($modelClass = "\common\models\RefundedItem")
    {
        return $this->getRefundedItems($modelClass)
            ->sum('item_price');
    }

    /**
     * Gets query for [[RefundedItems]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRefundedItems($modelClass = "\common\models\RefundedItem")
    {
        return $this->hasMany($modelClass::className(), ['order_uuid' => 'order_uuid']);
    }

    /**
     * Gets query for [[Refunds]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getRefunds($modelClass = "\common\models\Refund")
    {
        return $this->hasMany($modelClass::className(), ['order_uuid' => 'order_uuid']);
    }

    /**
     * Gets query for [[BusinessLocation]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getBusinessLocation($modelClass = "\common\models\BusinessLocation")
    {
        return $this->hasOne($modelClass::className(), ['business_location_id' => 'business_location_id'])
            ->andWhere(['business_location.is_deleted' => 0])
            ->via('deliveryZone');
    }

    /**
     * Gets query for [[PickupLocation]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getPickupLocation($modelClass = "\common\models\BusinessLocation")
    {
        return $this->hasOne($modelClass::className(), ['business_location_id' => 'pickup_location_id']);
    }

    public function getPaymentMethodName()
    {
        if ($this->paymentMethod)
            return $this->paymentMethod->payment_method_name;
        else if (!empty($this->payment_method_name))
            return $this->payment_method_name;
        else if (!empty($this->payment_method_name_ar))
            return $this->payment_method_name_ar;
    }

    /**
     * Gets query for [[Voucher]].
     *
     * @return \yii\db\ActiveQuery
     */
    public function getVoucher($modelClass = "\common\models\Voucher")
    {
        return $this->hasOne($modelClass::className(), ['voucher_id' => 'voucher_id']);
    }
}
