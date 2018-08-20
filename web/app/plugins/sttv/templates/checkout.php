<?php
function checkout_template(){
    $countrydd = get_option('sttv_country_options');
    $plans = (function(){
        return 'Hello World';
    })();

    return [
        '',
<<<HTML
<div id="step-1" class="stFormStep row">
    <div class="stFormHeader col s12">
        <h2>Okay, let's get started!</h2>
        <span>Create your account below. Don't worry, we do not and will not abuse, misuse, or sell your information. In fact, we don't even store it on our servers! Read our privacy policy for more info.</span>
    </div>
    <div id="stSignupAccount" class="stFormBody col s12">
        <div class="input-field required col s12 m6 st-input-half-left">
            <input class="browser-default validate" type="text" name="st-customer-account-firstname" placeholder="First Name" required />
        </div>
        <div class="input-field required col s12 m6 st-input-half-right">
            <input class="browser-default validate" type="text" name="st-customer-account-lastname" placeholder="Last Name" required/>
        </div>
        <div class="input-field required col s12">
            <input class="browser-default validate email" type="email" name="st-customer-account-email" placeholder="Email Address" required/>
        </div>
        <div class="input-field required col s12">
            <input class="browser-default validate" type="password" name="st-customer-account-password" placeholder="Password" required/>
        </div>
    </div>
    <div class="stFormButtons col s12">
        <button id="stBtn_account" class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.next(this.id)">Next >></button>
    </div>
</div>
HTML
,<<<HTML
<div id="step-2" class="stFormStep row">
    <div class="stFormHeader col s12">
        <h2>Cool, now select a plan.</h2>
        <span>All plans come with a 5 day free trial. <strong>NOTE:</strong> Your card will not be charged until your trial period is over, and you're free to cancel at any time. If your course comes with free books, they will not ship until your trial has expired.</span>
    </div>
    <div id="stSignupPlans" class="stFormBody col s12">$plans</div>
    <div class="stFormButtons col s12">
        <a class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.prev()"><< Back</a>
        <a id="stBtn_plan" class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.next(this.id)">Next >></a>
    </div>
</div>
HTML
,<<<HTML
<div id="step-3" class="stFormStep row">
    <div class="stFormHeader col s12">
        <h2>What's your billing address?</h2>
        <span>This is the address associated with the card you are going to use for payment. We use this to verify your payment, so please check the accuracy of the information you provide.</span>
    </div>
    <div id="stSignupBilling" class="stFormBody col s12">
        <div class="input-field required col s12">
            <input class="browser-default validate billing address1" type="text" name="st-customer-billing-address_line1" placeholder="Address 1" required/>
        </div>
        <div class="input-field col s12">
            <input class="browser-default validate billing address2" type="text" name="st-customer-billing-address_line2" placeholder="Address 2"/>
        </div>
        <div class="input-field required col s12 m6 st-input-half-left">
            <input class="browser-default validate billing city" type="text" name="st-customer-billing-address_city" placeholder="City" required/>
        </div>
        <div class="input-field required col s12 m6 st-input-half-right">
            <input class="browser-default validate billing state" type="text" name="st-customer-billing-address_state" placeholder="State" required/>
        </div>
        <div class="input-field required col s12 m6 st-input-half-left">
            <input class="browser-default validate billing pcode" type="text" name="st-customer-billing-address_zip" placeholder="Postal Code" required/>
        </div>
        <div class="input-field required col s12 m6 st-input-half-right">
            <select class="browser-default validate billing country" name="st-customer-billing-address_country" required>
                <option value selected>Country...</option>
                {$countrydd}
            </select>
        </div>
    </div>
    <div class="stFormButtons col s12">
        <a class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.prev()"><< Back</a>
        <a id="stBtn_void" class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.next(this.id)">Next >></a>
    </div>
</div>
HTML
,<<<HTML
<div id="step-4" class="stFormStep row">
    <div class="stFormHeader col s12">
        <h2>Where are we sending your books?</h2>
        <span>Even if you're signing up for a course that doesn't ship books, we still collect this information to keep on file in your account. We never share this information with anyone.</span>
    </div>
    <div id="stSignupShipping" class="stFormBody col s12">
        <div class="st-checkout-spaced col s12">
            <label>
                <input name="st-customer-options-copyAddress" class="filled-in" value="1" type="checkbox" onclick="_st.signup.copyAddress(this)"/>
                <span>Same as billing address</span>
            </label>
        </div>
        <div class="st-checkout-spaced col s12">
            <label>
                <input name="st-customer-options-priorityShip" class="filled-in" value="1" type="checkbox" onclick="_st.signup.setShipping(this)"/>
                <span>I want Priority Shipping (+$7.05, U.S. only)</span>
            </label>
        </div>
        <div class="input-field required col s12">
            <input class="browser-default validate shipping address_line1" type="text" name="st-customer-shipping-address-line1" placeholder="Address 1" required/>
        </div>
        <div class="input-field col s12">
            <input class="browser-default validate shipping address_line2" type="text" name="st-customer-shipping-address-line2" placeholder="Address 2" />
        </div>
        <div class="input-field required col s12 m6 st-input-half-left">
            <input class="browser-default validate shipping address_city" type="text" name="st-customer-shipping-address-city" placeholder="City" required/>
        </div>
        <div class="input-field required col s12 m6 st-input-half-right">
            <input class="browser-default validate shipping address_state" type="text" name="st-customer-shipping-address-state" placeholder="State" required/>
        </div>
        <div class="input-field required col s12 m6 st-input-half-left">
            <input class="browser-default validate shipping address_zip tax" onblur="_st.checkout.setChecker(this)" type="text" name="st-customer-shipping-address-postal_code" placeholder="Postal Code" required/>
        </div>
        <div class="input-field required col s12 m6 st-input-half-right">
            <select class='browser-default validate shipping address_country' name='st-customer-shipping-address-country' required>
                <option value disabled selected>Country...</option>
                {$countrydd}
            </select>
        </div>
    </div>
    <div class="stFormButtons col s12">
        <a class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.checkout.prev()"><< Back</a>
        <a id="stBtn_shipping" class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.next(this.id)">Next >></a>
    </div>
</div>
HTML
,<<<HTML
<div id="step-5" class="stFormStep">
    <div class="stFormHeader col s12">
        <h2>Almost there!</h2>
        <span>Your total is below. Does everything look correct? If so, enter your credit card info and then hit submit! It's that easy! (Remember, you will not be charged until your trial period expires. If you'd like to have full access right away, you can skip the trial by checking the box below.)</span>
    </div>
    <div id="stSignupPayment" class="stFormBody col s12">
        <div id="st-checkout-items-table" class="col s12">
            <div class="row headings-row">
                <div class="col s8">Item</div>
                <div class="col s4 right-align">Price</div>
            </div>
            <div class="items-row"></div>
            <div class="row totals-row valign-wrapper">
                <div class="col s6">
                    <div class="input-field coupon col s12">
                        <input class="browser-default coupon" name="st-coupon-val" type="text" placeholder="Coupon code" onblur="_st.checkout.setChecker(this)"/>
                    </div>
                </div>
                <div id="total" class="col s6 right-align"><span id="ttltxt">$<span>0</span></span></div>
            </div>
        </div>
        <div class="st-checkout-options col s12">
            <div class="st-checkout-spaced col s12">
                <label>
                    <input name="st-customer-options-skipTrial" class="filled-in" value="1" type="checkbox" />
                    <span>Skip the trial period and start NOW!</span>
                </label>
            </div>
            <div class="st-checkout-spaced required col s12">
                <label>
                    <input name="st-customer-options-terms" class="filled-in" value="1" type="checkbox" required/>
                    <span>I have read SupertutorTV's Terms & Conditions</span>
                </label>
            </div>
            <div class="st-checkout-spaced col s12">
                <label>
                    <input name="st-customer-options-mailinglist" class="filled-in" value="1" type="checkbox" />
                    <span>Add me to the SupertutorTV mailing list for future discounts and offers</span>
                </label>
            </div>
        </div>
        <div class="input-field required col s12 m6 st-input-half-left">
            <input class="browser-default validate" type="text" name="st-customer-billing-name" placeholder="Name on card" required/>
        </div>
        <div class="input-field required col s12 m6 st-input-half-right">
                <input class="browser-default validate" type="tel" name="st-customer-shipping-phone" placeholder="Phone Number" required/>
                <label></label>
        </div>
        <div id="stSignupCardElement" class="col s12"></div>
        <script>if (_st.signup.card === false) _st.signup.cardSetup()</script>
    </div>
    <div class="stFormButtons col s12">
        <a class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.checkout.prev()"><< Back</a>
        <a id="stSignupSubmit" class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.checkout.submit()">SUBMIT</a>
    </div>
</div>
HTML
    ];
};