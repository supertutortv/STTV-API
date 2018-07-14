<div id="st-login-wrapper" class="col s12">
    <div id="pane-1" class="st-login-pane row active">
        <div class="st-login-header col s12">
            <h2>Please sign into your account!</h2>
            <span>You can access all of your account information, as well as your test prep courses, by logging in below. <br><a onclick="_st.login.next()">Need to reset your password?</a></span>
        </div>
        <div id="st-login-credentials" class="st-login-form col s12 l6 push-l3">
            <div class="input-field required col s12">
                <input class="browser-default validate email" type="email" name="st-username" placeholder="Email Address"/>
            </div>
            <div class="input-field required col s12">
                <input class="browser-default validate" type="password" name="st-password" placeholder="Password"/>
            </div>
        </div>
        <div class="st-login-buttons col s12">
            <button class="st-login-btn pmt-button btn waves-effect waves-light" onclick="_st.login.send()">Login</button>
        </div>
    </div>
    <div id="pane-2" class="st-login-pane row">
        <div class="st-login-header col s12">
            <h2>Reset your password</h2>
            <span>Enter the email associated with your account below. You will be sent an email with a link to reset your password, then you can just come back here and sign in again to access your account.</span>
        </div>
        <div id="st-login-rp-form" class="st-login-form col s12 l6 push-l3">
            <div class="input-field required col s12">
                <input class="browser-default validate email" type="email" name="st-rp-username" placeholder="Email Address" required/>
            </div>
        </div>
        <div class="st-login-buttons col s12">
            <button class="st-login-btn pmt-button btn waves-effect waves-light" onclick="_st.login.prev()"><< Login</button>
            <button class="st-login-btn pmt-button btn waves-effect waves-light" onclick="_st.login.reset()">Reset</button>
        </div>
    </div>
</div>