<div id="step-4" class="stFormStep row">
    <div class="stFormHeader col s12">
        <h2>Where are we sending your books?</h2>
        <span>Even if you're signing up for a course that doesn't ship books, we still collect this information to keep on file in your account with our payment processor. We never share this information with anyone.</span>
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
            <input class="browser-default validate shipping address_zip tax" onblur="_st.signup.setChecker(this)" type="text" name="st-customer-shipping-address-postal_code" placeholder="Postal Code" required/>
        </div>
        <div class="input-field required col s12 m6 st-input-half-right">
            <select class='browser-default validate shipping address_country' name='st-customer-shipping-address-country' required>
                <option value disabled selected>Country...</option>
                <?php print get_option('sttv_country_options'); ?>
            </select>
        </div>
    </div>
    <div class="stFormButtons col s12">
        <a class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.checkout.prev()"><< Back</a>
        <a id="stBtn_shipping" class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.next(this.id,'renderPayment')">Next >></a>
    </div>
</div>