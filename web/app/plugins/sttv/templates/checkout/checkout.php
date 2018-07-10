<?php
function checkout_template(){
$countrydd = get_option('sttv_country_options');
$ck1 = <<<HTML
<div id="pane-1" class="st-checkout-pane row">
    <div class="st-checkout-header col s12">
        <h2>Okay, let's get started!</h2>
        <span>You're very close to getting expert tutoring from Brooke Hanson. <strong>NOTE:</strong> Your card will not be charged until your trial period is over, and you're free to cancel at any time. If your course comes with free books, they will not ship until your trial has expired.</span>
    </div>
    <div id="st-checkout-account" class="st-checkout-form col s12 l8 push-l2">
        <div class="input-field col s12 l6 st-input-half-left">
            <label></label>
            <input class="browser-default validate" type="text" name="st-customer-firstname" placeholder="First Name" required />
        </div>
        <div class="input-field col s12 l6 st-input-half-right">
            <input class="browser-default validate" type="text" name="st-customer-lastname" placeholder="Last Name" required/>
        </div>
        <div class="input-field col s12">
            <input class="browser-default validate" type="email" name="st-customer-email" placeholder="Email Address" required/>
        </div>
        <div class="input-field col s12">
            <input class="browser-default validate" type="password" name="st-customer-password" placeholder="Password" required/>
        </div>
    </div>
    <div class="st-checkout-errors col s12"></div>
    <div class="st-checkout-buttons col s12">
        <a class="st-checkout-next st-checkout-btn pmt-button btn waves-effect waves-light" onclick="_st.checkout.next()">Next >></a>
    </div>
</div>
HTML;

$ck2 = <<<HTML
<div id="pane-2" class="st-checkout-pane">
    <div class="st-checkout-header col s12">
        <h2>What's your billing address?</h2>
        <span>This is the address associated with the card you are going to use for payment. We use this to verify your payment, so please check the accuracy of the information you provide.</span>
    </div>
    <div id="st-checkout-billing" class="st-checkout-form col s12 l8 push-l2">
        <div class="input-field col s12">
            <input class="browser-default validate billing address1" type="text" name="st-customer-billing-address_line1" placeholder="Address 1" required/>
        </div>
        <div class="input-field col s12">
            <input class="browser-default validate billing address2" type="text" name="st-customer-billing-address_line2" placeholder="Address 2"/>
        </div>
        <div class="input-field col s12 l6 st-input-half-left">
            <input class="browser-default validate billing city" type="text" name="st-customer-billing-address_city" placeholder="City" required/>
        </div>
        <div class="input-field col s12 l6 st-input-half-right">
            <input class="browser-default validate billing state" type="text" name="st-customer-billing-address_state" placeholder="State" required/>
        </div>
        <div class="input-field col s12 l6 st-input-half-left">
            <input class="browser-default validate billing pcode" type="text" name="st-customer-billing-address_zip" placeholder="Postal Code" required/>
        </div>
        <div class="input-field col s12 l6 st-input-half-right">
            <select class="browser-default validate billing country" name="st-customer-billing-address_country" required>
                <option value selected>Country...</option>
                {$countrydd}
            </select>
        </div>
    </div>
    <div class="st-checkout-buttons col s12">
        <a class="st-checkout-prev st-checkout-btn pmt-button btn waves-effect waves-light" onclick="_st.checkout.prev()"><< Back</a>
        <a class="st-checkout-next st-checkout-btn pmt-button btn waves-effect waves-light" onclick="_st.checkout.next()">Next >></a>
    </div>
</div>
HTML;

$ck3 = <<<HTML
<div id="pane-3" class="st-checkout-pane">
    <div class="st-checkout-header col s12">
        <h2>Where are we sending your books?</h2>
        <span>Even if you're signing up for a course that doesn't ship books, we still collect this information to keep on file in your account. We never share this information with anyone.</span>
    </div>
    <div id="st-checkout-shipping" class="st-checkout-form col s12 l8 push-l2">
        <div class="st-checkout-spaced col s12">
            <label>
                <input name="st-customer-shipping-copy-billing" class="filled-in" type="checkbox" />
                <span>Same as billing address</span>
            </label>
        </div>
        <div class="st-checkout-spaced col s12">
            <label>
                <input name="st-customer-shipping-priority" class="filled-in" type="checkbox" />
                <span>I want Priority Shipping (+$7.05, U.S. only)</span>
            </label>
        </div>
        <div class="input-field col s12">
            <input class="browser-default validate shipping address_line1" type="text" name="st-customer-shipping-address-line1" placeholder="Address 1" required/>
        </div>
        <div class="input-field col s12">
            <input class="browser-default validate shipping address_line2" type="text" name="st-customer-shipping-address-line2" placeholder="Address 2" />
        </div>
        <div class="input-field col s12 l6 st-input-half-left">
            <input class="browser-default validate shipping address_city" type="text" name="st-customer-shipping-address-city" placeholder="City" required/>
        </div>
        <div class="input-field col s12 l6 st-input-half-right">
            <input class="browser-default validate shipping address_state" type="text" name="st-customer-shipping-address-state" placeholder="State" required/>
        </div>
        <div class="input-field col s12 l6 st-input-half-left">
            <input class="browser-default validate shipping address_zip" type="text" name="st-customer-shipping-address-postal_code" placeholder="Postal Code" required/>
        </div>
        <div class="input-field col s12 l6 st-input-half-right">
            <select class='browser-default validate shipping address_country' name='st-customer-shipping-address-country' required>
                <option value disabled selected>Country...</option>
                {$countrydd}
            </select>
        </div>
    </div>
    <div class="st-checkout-buttons col s12">
        <a class="st-checkout-prev st-checkout-btn pmt-button btn waves-effect waves-light" onclick="_st.checkout.prev()"><< Back</a>
        <a class="st-checkout-next st-checkout-btn pmt-button btn waves-effect waves-light" onclick="_st.checkout.next()">Next >></a>
    </div>
</div>
HTML;

$ck4 = <<<HTML
<div id="pane-4" class="st-checkout-pane">
    <div class="st-checkout-header col s12">
        <h2>Almost there!</h2>
        <span>Your total is below. Does everything look correct? If so, enter your credit card info and then hit submit! It's that easy! (Remember, you will not be charged until your {{trial}} day trial period is up.)</span>
    </div>
    <div id="st-checkout-payment" class="st-checkout-form col s12 l8 push-l2">
        <div id="st-checkout-items-table" class="col s12">
            <div class="row headings-row">
                <div class="col s2">Qty</div>
                <div class="col s8">Item</div>
                <div class="col s2 right-align">Price</div>
            </div>
            <div class="items-row"></div>
            <div class="row totals-row valign-wrapper">
                <div class="col s6">
                    <div class="input-field coupon col s12">
                        <input class="browser-default" name="st-coupon" type="text" placeholder="Coupon code"/>
                    </div>
                </div>
                <div id="total" class="col s6 right-align"><span id="ttltxt">$<span>0</span></span></div>
            </div>
        </div>
        <div class="st-checkout-options row">
            <div class="st-checkout-spaced col s12">
                <label>
                    <input name="st-customer-terms" class="filled-in" type="checkbox" required/>
                    <span>I have read SupertutorTV's Terms & Conditions</span>
                </label>
            </div>
        </div>
        <div class="input-field col s12 l6 st-input-half-left">
            <input class="browser-default validate" type="text" name="st-customer-name" placeholder="Name on card" required/>
        </div>
        <div class="input-field col s12 l6 st-input-half-right">
                <input class="browser-default validate" type="tel" name="st-customer-shipping-phone" placeholder="Phone Number" required/>
                <label></label>
        </div>
        <div id="st-checkout-card-element" class="col s12"></div>
        <script>if (!_st.checkout.card) _st.checkout.setup()</script>
    </div>
    <div class="st-checkout-buttons col s12">
        <a class="st-checkout-prev st-checkout-btn pmt-button btn waves-effect waves-light" onclick="_st.checkout.prev()"><< Back</a>
        <a class="st-checkout-submit st-checkout-btn pmt-button btn waves-effect waves-light" onclick="_st.checkout.submit()">SUBMIT</a>
    </div>
</div>
HTML;

return [
    '',
    $ck1,
    $ck2,
    $ck3,
    $ck4
];
};