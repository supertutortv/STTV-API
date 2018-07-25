<div id="st-musignup-wrapper" class="col s12">
    <div id="pane-1" class="st-musignup-pane row">
        <div class="st-musignup-header col s12">
            <h2>{{title}}</h2>
            <span>Enter your information and multi-user key below to get started.</span>
        </div>
        <div id="st-musignup-account" class="st-musignup-form col s12 l8 push-l2">
            <div class="input-field required col s12">
                <input class="browser-default validate" autocomplete="off" type="text" name="st-mukey" placeholder="Multi-user Key" onblur="_st.mu.setState(this)" required/>
            </div>
            <div class="input-field required col s12 m6 st-input-half-left">
                <input class="browser-default validate" autocomplete="off" type="text" name="st-firstname" placeholder="First Name" onblur="_st.mu.setState(this)" required />
            </div>
            <div class="input-field required col s12 m6 st-input-half-right">
                <input class="browser-default validate" autocomplete="off" type="text" name="st-lastname" placeholder="Last Name" onblur="_st.mu.setState(this)" required/>
            </div>
            <div class="input-field required col s12">
                <input class="browser-default validate email" autocomplete="off" type="email" name="st-email" placeholder="Email Address" onblur="_st.mu.setState(this)" required/>
            </div>
            <div class="input-field required col s12">
                <input class="browser-default validate" autocomplete="off" type="password" name="st-password" placeholder="Password" onblur="_st.mu.setState(this)" required/>
            </div>
        </div>
        <div class="st-musignup-buttons col s12">
            <button class="st-musignup-submit st-musignup-btn pmt-button btn waves-effect waves-light" onclick="_st.mu.submit()">Submit</button>
        </div>
    </div>
</div>