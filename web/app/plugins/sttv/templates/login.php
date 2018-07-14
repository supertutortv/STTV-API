<div id="st-login-wrapper" class="col s12">
    <div id="pane-1" class="st-login-pane row active">
        <div class="st-login-header col s12">
            <h2>Please sign into your account!</h2>
            <span>Here's some random text to tell everyone how awesome they are and what not. <a onclick="javascript:void(0)">Need to reset your password?</a></span>
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
            <a class="st-login-next st-login-btn pmt-button btn waves-effect waves-light" onclick="_st.login.send()">Login</a>
        </div>
    </div>
    <div id="pane-2" class="st-login-pane row">
        <div class="st-login-header col s12">
            <h2>Reset your password</h2>
            <span>Here's some random text to tell everyone how awesome they are and what not. <a href="#" onclick="javascript:void(0)">Need to reset your password?</a></span>
        </div>
        <div id="st-login-rp-form" class="st-login-form col s12 l6 push-l3">
            <div class="input-field required col s12">
                <input class="browser-default validate email" type="email" name="st-rp-username" placeholder="Email Address" required/>
            </div>
        </div>
        <div class="st-login-buttons col s12">
            <a class="st-login-next st-login-btn pmt-button btn waves-effect waves-light" onclick="_st.login.reset()">Reset</a>
        </div>
    </div>
</div>