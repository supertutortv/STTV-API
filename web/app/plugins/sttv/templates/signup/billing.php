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
                <?php print get_option('sttv_country_options'); ?>
            </select>
        </div>
    </div>
    <div class="stFormButtons col s12">
        <a class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.prev()"><< Back</a>
        <a id="stBtn_billing" class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.next(this.id)">Next >></a>
    </div>
</div>