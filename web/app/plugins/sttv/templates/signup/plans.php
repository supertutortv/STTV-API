<div id="step-2" class="stFormStep row">
    <div class="stFormHeader col s12">
        <h2>Cool, now select a plan.</h2>
        <span>All plans come with a 5 day free trial. <strong>NOTE:</strong> Your card will not be charged until your trial period is over, and you're free to cancel at any time. If your course comes with free books, they will not ship until your trial has expired.</span>
    </div>
    <div id="stSignupPlans" class="stFormBody col s12">
    <?php 
        $subs = get_posts(['post_type' => 'subscriptions','numberposts' => -1]);
        foreach ($subs as $sub) { ?>
            <a id="stBtn_plan-<?php echo sttv_id_encode($sub->ID); ?>" class="stPlan <?php echo $sub->post_name; ?>" onclick="_st.signup.plan(this.id)"><?php echo $sub->post_title; ?></a>
       <?php }
    ?>
    </div>
    <div class="stFormButtons col s12">
        <a class="stFormButton pmt-button btn waves-effect waves-light" onclick="_st.signup.prev()"><< Back</a>
        <a id="stBtn_plan" class="stFormButton pmt-button btn waves-effect waves-light" >Next >></a>
    </div>
</div>