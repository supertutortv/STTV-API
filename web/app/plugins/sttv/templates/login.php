<div id="st-checkout-wrapper" class="col s12">
    <div id="pane-1" class="st-checkout-pane row">
        <div class="st-checkout-header col s12">
            <h2>Log in to your account!</h2>
        </div>
        <div id="st-checkout-account" class="st-checkout-form col s12 l8 push-l2">
            <div class="input-field required col s12">
                <input class="browser-default validate email" type="email" name="st-login-username" placeholder="Email Address" required/>
            </div>
            <div class="input-field required col s12">
                <input class="browser-default validate" type="password" name="st-login-password" placeholder="Password" required/>
            </div>
        </div>
        <div class="st-checkout-errors col s12"></div>
        <div class="st-checkout-buttons col s12">
            <a class="st-checkout-next st-checkout-btn pmt-button btn waves-effect waves-light" onclick="_st.logout.send()">Login</a>
        </div>
    </div>
</div>