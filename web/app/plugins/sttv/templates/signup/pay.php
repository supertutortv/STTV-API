<div id="step-5" class="stFormStep">
    <div class="stFormHeader col s12">
        <h2>Almost there!</h2>
        <span>Your total is below. Does everything look correct? If so, enter your credit card info and then hit submit! It's that easy! (Remember, you will not be charged until your trial period expires. If you'd like to have full access right away, you can skip the trial by checking the box below.)</span>
    </div>
    <div id="stSignupPayment" class="stFormBody col s12">
        <div id="stSignupItemsTable" class="col s12">
            <div class="row headings-row">
                <div class="col s8">Item</div>
                <div class="col s4 right-align">Price</div>
            </div>
            <div class="itemsRow"></div>
            <div class="row totals-row valign-wrapper">
                <div class="col s6">
                    <div class="input-field coupon col s12">
                        <input class="browser-default coupon" name="st-pricing-coupon-value" type="text" placeholder="Coupon code" onblur="_st.signup.setChecker(this)"/>
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
                    <input id="stTermsBox" name="st-customer-options-terms" class="filled-in" value="1" type="checkbox" onchange="_st.signup.setOutcome()" required/>
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
    </div>
    <div class="stFormButtons col s12">
        <a class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.prev()"><< Back</a>
        <a id="stSignupSubmit" class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.pay()" disabled>Pay</a>
    </div>
</div>