<?php

/**
* Main model class for all bookings, this handles all the data
*/
class WC_Booking {

	/** @public int */
	public $id;

	/** @public string */
	public $booking_date;

	/** @public string */
	public $start;

	/** @public string */
	public $end;

	/** @public bool */
	public $all_day;

	/** @public string */
	public $modified_date;

	/** @public object */
	public $post;

	/** @public int */
	public $product_id;

	/** @public object */
	public $product;

	/** @public int */
	public $order_id;

	/** @public object */
	public $order;

	/** @public int */
	public $customer_id;

	/** @public string */
	public $status;

	/** @public array - contains all post meta values for this booking */
	public $custom_fields;

	/** @public bool */
	public $populated;

	/** @private array - used to temporarily hold order data for new bookings */
	private $order_data;

	/**
	 * Constructor, possibly sets up with post or id belonging to existing booking
	 * or supplied with an array to construct a new booking
	 * @param int/array/obj $booking_data
	 */
	public function __construct( $booking_data = false ) {
		$populated = false;

		if ( is_array( $booking_data ) ) {
			$this->order_data = $booking_data;
			$populated = false;
		} else if ( is_int( intval( $booking_data ) ) && 0 < $booking_data ) {
			$populated = $this->populate_data( $booking_data );
		} else if ( is_object( $booking_data ) && isset( $booking_data->ID ) ) {
			$this->post = $booking_data;
			$populated = $this->populate_data( $booking_data->ID );
		}

		$this->populated = $populated;
	}

	/**
	 * Actual create for the new booking belonging to an order
	 * @param string Status for new order
	 */
	public function create( $status = 'unpaid' ) {
		$this->new_booking( $status, $this->order_data );
		$this->schedule_events();
	}

	/**
	 * Schedule events for this booking
	 */
	public function schedule_events() {
		if ( in_array( get_post_status( $this->id ), get_wc_booking_statuses( 'scheduled' ) ) ) {
			if ( $this->start && $this->get_order() ) {
				$order_status = $this->get_order()->get_status();
				if ( ! in_array( $order_status, array( 'cancelled', 'refunded', 'pending', 'on-hold' ) ) ) {
					wp_schedule_single_event( strtotime( '-' . absint( apply_filters( 'woocommerce_bookings_remind_before_days', 1 ) ) . ' day', $this->start ), 'wc-booking-reminder', array( $this->id ) );
				}
			}
			if ( $this->end ) {
				wp_schedule_single_event( $this->end, 'wc-booking-complete', array( $this->id ) );
			}
		} else {
			wp_clear_scheduled_hook( 'wc-booking-reminder', array( $this->id ) );
			wp_clear_scheduled_hook( 'wc-booking-complete', array( $this->id ) );
		}
	}

	/**
	 * Makes the new booking belonging to an order
	 * @param string $status The status for this new booking
	 * @param array $order_data Array with all the new order data
	 */
	private function new_booking( $status, $order_data ) {
		global $wpdb;

		$order_data = wp_parse_args( $order_data, array(
			'user_id'           => 0,
			'resource_id'       => '',
			'product_id'        => '',
			'order_item_id'     => '',
			'persons'           => array(),
			'cost'              => '',
			'start_date'        => '',
			'end_date'          => '',
			'all_day'           => 0,
			'parent_id'         => 0,
		) );

		// Get parent data
		if ( $order_data['parent_id'] ) {
			if ( ! $order_data['order_item_id'] ) {
				$order_data['order_item_id'] = get_post_meta( $order_data['parent_id'], '_booking_order_item_id', true );
			}

			if ( ! $order_data['user_id'] ) {
				$order_data['user_id'] = get_post_meta( $order_data['parent_id'], '_booking_customer_id', true );
			}
		}

		// Get order ID from order item
		if ( $order_data['order_item_id'] ) {
			$order_id = $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $order_data['order_item_id'] ) );
		} else {
			$order_id = 0;
		}

		$booking_data = array(
			'post_type'   => 'wc_booking',
			'post_title'  => sprintf( __( 'Booking &ndash; %s', 'woocommerce-bookings' ), strftime( _x( '%b %d, %Y @ %I:%M %p', 'Booking date parsed by strftime', 'woocommerce-bookings' ) ) ),
			'post_status' => $status,
			'ping_status' => 'closed',
			'post_parent' => $order_id
		);

		$this->id = wp_insert_post( $booking_data );

		// Setup the required data for the current user
		if ( ! $order_data['user_id'] ) {
			if ( is_user_logged_in() ) {
				$order_data['user_id'] = get_current_user_id();
			} else {
				$order_data['user_id'] = 0;
			}
		}

		// Convert booking start and end to requried format
		if ( is_numeric( $order_data['start_date'] ) ) {
			// Convert timestamp
			$order_data['start_date'] = date( 'YmdHis', $order_data['start_date'] );
			$order_data['end_date']   = date( 'YmdHis', $order_data['end_date'] );
		} else {
			$order_data['start_date'] = date( 'YmdHis', strtotime( $order_data['start_date'] ) );
			$order_data['end_date']   = date( 'YmdHis', strtotime( $order_data['end_date'] ) );
		}

		$meta_args = array(
			'_booking_order_item_id' => $order_data['order_item_id'],
			'_booking_product_id'    => $order_data['product_id'],
			'_booking_resource_id'   => $order_data['resource_id'],
			'_booking_persons'       => $order_data['persons'],
			'_booking_cost'          => $order_data['cost'],
			'_booking_start'         => $order_data['start_date'],
			'_booking_end'           => $order_data['end_date'],
			'_booking_all_day'       => intval( $order_data['all_day'] ),
			'_booking_parent_id'     => $order_data['parent_id'],
			'_booking_customer_id'   => $order_data['user_id'],
		);

		foreach ( $meta_args as $key => $value ) {
			update_post_meta( $this->id, $key, $value );
		}

		WC_Cache_Helper::get_transient_version( 'bookings', true );

		do_action( 'woocommerce_booking_' . $status, $this->id );
		do_action( 'woocommerce_new_booking', $this->id );
	}

	/**
	 * Assign this booking to an order and order item by ID
	 * @param int $order_id
	 * @param int $order_item_id
	 */
	public function set_order_id( $order_id, $order_item_id ) {
		$this->order_id = $order_id;
		wp_update_post( array( 'ID' => $this->id, 'post_parent' => $this->order_id ) );
		update_post_meta( $this->id, '_booking_order_item_id', $order_item_id );
	}

	/**
	 * Populate the data with the id of the booking provided
	 * Will query for the post belonging to this booking and store it
	 * @param int $booking_id
	 */
	public function populate_data( $booking_id ) {
		if ( ! isset( $this->post ) ) {
			$post = get_post( $booking_id );
		}

		if ( is_object( $post ) ) {
			// We have the post object belonging to this booking, now let's populate
			$this->id            = $post->ID;
			$this->booking_date  = $post->post_date;
			$this->modified_date = $post->post_modified;
			$this->customer_id   = $post->post_author;
			$this->custom_fields = get_post_meta( $this->id );
			$this->status        = $post->post_status;
			$this->order_id      = $post->post_parent;

			// Define the data we're going to load: Key => Default value
			$load_data = array(
				'product_id'  => '',
				'resource_id' => '',
				'persons'     => array(),
				'cost'        => '',
				'start'       => '',
				'customer_id' => '',
				'end'         => '',
				'all_day'     => 0,
				'parent_id'   => 0,
			);

			// Load the data from the custom fields (with prefix for this plugin)
			$meta_prefix = '_booking_';

			foreach ( $load_data as $key => $default ) {
				if ( isset( $this->custom_fields[ $meta_prefix . $key ][0] ) && $this->custom_fields[ $meta_prefix . $key ][0] !== '' ) {
					$this->$key = maybe_unserialize( $this->custom_fields[ $meta_prefix . $key ][0] );
				} else {
					$this->$key = $default;
				}
			}

			// Start and end date converted to timestamp
			$this->start = strtotime( $this->start );
			$this->end   = strtotime( $this->end );

			// Save the post object itself for future reference
			$this->post = $post;
			return true;
		}

		return false;
	}

	/**
	 * Will change the booking status once the order is paid for
	 * @return bool
	 */
	public function paid() {
		$current_status = $this->status;
		$event          = wp_get_schedule( 'wc-booking-reminder', array( $this->id ) );

		if ( $this->populated && in_array( $current_status, array( 'unpaid', 'confirmed' ) ) ) {
			$this->update_status( 'paid' );

			if ( ! empty( $event ) ) {
				$this->schedule_events();
			}

			return true;
		}

		return false;
	}

	/**
	 * Set the new status for this booking
	 * @param string $status
	 * @return bool
	 */
	public function update_status( $status ) {
		$current_status   = $this->get_status( true );
		$allowed_statuses = array_unique( array_merge( get_wc_booking_statuses(), get_wc_booking_statuses( 'user' ), get_wc_booking_statuses( 'cancel' ) ) );
		$allowed_statuses[] = 'was-in-cart';
		$allowed_statuses = array_values( $allowed_statuses );

		if ( $this->populated ) {
			if ( in_array( $status, $allowed_statuses ) ) {
				wp_update_post( array( 'ID' => $this->id, 'post_status' => $status ) );

				// Reschedule cron
				$this->schedule_events();

				// Trigger actions
				do_action( 'woocommerce_booking_' . $current_status . '_to_' . $status, $this->id );
				do_action( 'woocommerce_booking_' . $status, $this->id );

				// Note in the order
				if ( $order = $this->get_order() ) {
					$order->add_order_note( sprintf( __( 'Booking #%d status changed from "%s" to "%s"', 'woocommerce-bookings' ), $this->id, $current_status, $status ) );
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * Checks the booking status against a passed in status.
	 *
	 * @return bool
	 */
	public function has_status( $status ) {
		return apply_filters( 'woocommerce_booking_has_status', ( is_array( $status ) && in_array( $this->get_status(), $status ) ) || $this->get_status() === $status ? true : false, $this, $status );
	}

	/**
	 * Returns the status of this booking
	 * @param Bool to ask for pretty status name (if false)
	 * @return String of the booking status
	 */
	public function get_status( $raw = true ) {
		if ( $this->populated ) {
			if ( $raw ) {
				return $this->status;
			} else {
				$status_object = get_post_status_object( $this->status );
				return $status_object->label;
			}
		}

		return false;
	}

	/**
	 * Returns the id of this booking
	 * @return Id of the booking or false if booking is not populated
	 */
	public function get_id() {
		if ( $this->populated ) {
			return $this->id;
		}

		return false;
	}

	/**
	 * Get the product ID for the booking
	 * @return int or false if booking is not populated
	 */
	public function get_product_id() {
		if ( $this->populated ) {
			return $this->product_id;
		}

		return false;
	}

	/**
	 * Returns the object of the order corresponding to this booking
	 * @return Product object or false if booking is not populated
	 */
	public function get_product() {
		if ( empty( $this->product ) ) {
			if ( $this->populated && $this->product_id ) {
				$this->product = get_product( $this->product_id );
			} else {
				return false;
			}
		}

		return $this->product;
	}

	/**
	 * Returns the object of the order corresponding to this booking
	 * @return Order object or false if booking is not populated
	 */
	public function get_order() {
		if ( empty( $this->order ) ) {
			if ( $this->populated && ! empty( $this->order_id ) && 'shop_order' === get_post_type( $this->order_id ) ) {
				$this->order = wc_get_order( $this->order_id );
			} else {
				return false;
			}
		}

		return $this->order;
	}

	/**
	 * Returns the cancel URL for a booking
	 *
	 * @param string $redirect
	 * @return string
	 */
	public function get_cancel_url( $redirect = '' ) {
		$cancel_page = get_permalink( wc_get_page_id( 'myaccount' ) );

		if ( ! $cancel_page ) {
			$cancel_page = home_url();
		}

		return apply_filters( 'bookings_cancel_booking_url', wp_nonce_url( add_query_arg( array( 'cancel_booking' => 'true', 'booking_id' => $this->id, 'redirect' => $redirect ), $cancel_page ), 'woocommerce-bookings-cancel_booking' ) );
	}

	/**
	 * Return if all day event
	 * @return boolean
	 */
	public function is_all_day() {
		if ( $this->populated ) {
			if ( $this->all_day ) {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}

	/**
	 * See if this booking is within a block
	 * @return boolean
	 */
	public function is_within_block( $block_start, $block_end ) {
		if ( $this->populated ) {
			if ( $this->start >= $block_end ) {
				return false; // Booking starts after block ends
			}

			if ( $this->end <= $block_start ) {
				return false; // Booking ends before block starts
			}

			return true;
		}
		return false;
	}

	/**
	 * See if this booking is booked on said date
	 * @return boolean
	 */
	public function is_booked_on_day( $block_start, $block_end ) {
		if ( $this->populated ) {
			$loop_date        = $this->start;
			$multiday_booking = date( 'Y-m-d', $this->start ) < date( 'Y-m-d', $this->end );

			if ( $multiday_booking ) {
				if ( date( 'YmdHi', $block_end ) > date( 'YmdHi', $this->start ) || date( 'YmdHi', $block_start ) < date( 'YmdHi', $this->end ) ) {
					return true;
				}
				return false;
			}

			while ( $loop_date <= $this->end ) {
				if ( date( 'Y-m-d', $loop_date ) === date( 'Y-m-d', $block_start ) ) {
					return true;
				}
				$loop_date = strtotime( "+1 day", $loop_date );
			}
		}
		return false;
	}

	/**
	 * See if this booking can still be cancelled by the user or not
	 * @return boolean
	 */
	public function passed_cancel_day() {
		$booking = $this->get_product();

		if ( ! $booking || ! $booking->can_be_cancelled() ) {
			return true;
		}

		if ( $booking !== false ) {
			$cancel_limit      = $booking->wc_booking_cancel_limit;
			$cancel_limit_unit = $cancel_limit > 1 ? $booking->wc_booking_cancel_limit_unit . 's' : $booking->wc_booking_cancel_limit_unit;
			$cancel_string     = sprintf( '%s +%d %s', current_time( 'd F Y H:i:s' ), $cancel_limit, $cancel_limit_unit );

			if ( strtotime( $cancel_string ) >= $this->start ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns booking start date
	 * @return string Date formatted via date_i18n
	 */
	public function get_start_date( $date_format = null, $time_format = null ) {
		if ( $this->populated && ! empty( $this->start ) ) {
			if ( is_null( $date_format ) ) {
				$date_format = apply_filters( 'woocommerce_bookings_date_format', wc_date_format() );
			}
			if ( is_null( $time_format ) ) {
				$time_format = apply_filters( 'woocommerce_bookings_time_format', ', ' . wc_time_format() );
			}
			if ( $this->is_all_day() ) {
				return date_i18n( $date_format, $this->start );
			} else {
				return apply_filters( 'woocommerce_bookings_get_start_date_with_time', date_i18n( $date_format . $time_format, $this->start ), $this );
			}
		}

		return false;
	}

	/**
	 * Returns booking end date
	 * @return string Date formatted via date_i18n
	 */
	public function get_end_date( $date_format = null, $time_format = null ) {
		if ( $this->populated && ! empty( $this->end ) ) {
			if ( is_null( $date_format ) ) {
				$date_format = apply_filters( 'woocommerce_bookings_date_format', wc_date_format() );
			}
			if ( is_null( $time_format ) ) {
				$time_format = apply_filters( 'woocommerce_bookings_time_format', ', ' . wc_time_format() );
			}
			if ( $this->is_all_day() ) {
				return date_i18n( $date_format, $this->end );
			} else {
				return apply_filters( 'woocommerce_bookings_get_end_date_with_time', date_i18n( $date_format . $time_format, $this->end ), $this );
			}
		}

		return false;
	}

	/**
	 * Returns information about the customer of this order
	 * @return array containing customer information
	 */
	public function get_customer() {
		if ( $this->populated ) {
			$order = $this->get_order();

			if ( $order )
				return (object) array(
					'name'    => trim( $order->billing_first_name . ' ' . $order->billing_last_name ),
					'email'   => $order->billing_email,
					'user_id' => $order->customer_user,
				);
			elseif ( $this->customer_id ) {
				$user = get_user_by( 'id', $this->customer_id );

				return (object) array(
					'name'    => $user->display_name,
					'email'   => $user->user_email,
					'user_id' => $this->customer_id
				);
			}
		}

		return false;
	}

	/**
	 * Returns if persons are enabled/needed for the booking product
	 * @return boolean
	 */
	public function has_persons() {
		return $this->get_product() ? $this->get_product()->has_persons() : false;
	}

	/**
	 * Returns if resources are enabled/needed for the booking product
	 * @return boolean
	 */
	public function has_resources() {
		return $this->get_product() ? $this->get_product()->has_resources() : false;
	}

	/**
	 * Return a array with the booking persons.
	 * @return array
	 */
	public function get_persons() {
		return (array) $this->persons;
	}

	/**
	 * Return the amount of persons for this booking.
	 * @return int
	 */
	public function get_persons_total() {
		return array_sum( $this->get_persons() );
	}

	/**
	 * Get the resource id
	 * @return int
	 */
	public function get_resource_id() {
		if ( $this->populated ) {
			return absint( $this->resource_id );
		}
		return 0;
	}

	/**
	 * Get the resource/type for this booking if applicable.
	 * @return bool|object WP_Post
	 */
	public function get_resource() {
		$resource_id = $this->get_resource_id();

		if ( ! $resource_id || ! ( $product = $this->get_product() ) || ! method_exists( $product, 'get_resource' ) ) {
			return false;
		}

		return $product->get_resource( $resource_id );
	}
}
