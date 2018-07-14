<div id="st-login-wrapper" class="col s12">
    <div id="pane-1" class="st-checkout-pane st-modal-form-pane row active" style="display:block;">
        <div class="st-modal-form-header col s12">
            <h2>Please sign into your account!</h2>
            <span>Here's some random text to tell everyone how awesome they are and what not. <a href="#" onclick="javascript:void(0)">Need to reset your password?</a></span>
        </div>
        <div id="st-login-credentials" class="st-checkout-form col s12 l8 push-l2">
            <div class="input-field required col s12">
                <input class="browser-default validate email" type="email" name="st-username" placeholder="Email Address" required/>
            </div>
            <div class="input-field required col s12">
                <input class="browser-default validate" type="password" name="st-password" placeholder="Password" required/>
            </div>
        </div>
        <div class="st-checkout-errors col s12"></div>
        <div class="st-checkout-buttons col s12">
            <a class="st-checkout-next st-checkout-btn pmt-button btn waves-effect waves-light" onclick="_st.login.send()">Login</a>
        </div>
    </div>
</div>