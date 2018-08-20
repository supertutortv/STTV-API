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