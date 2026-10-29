{{-- On every page of a PDF downloaded before anyone reviewed it. --}}
@unless ($reviewed)
    <div style="position: fixed; top: -34px; left: 0; right: 0; text-align: center; font-family: sans-serif; font-size: 8px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; color: #b45309;">
        Draft · not reviewed · written by Ventiq Assist, not yet checked by a person
    </div>
    <div style="margin: 0 0 18px; border: 1px solid #f59e0b; background: #fffbeb; padding: 8px 12px; font-family: sans-serif; font-size: 9.5px; line-height: 1.5; color: #92400e;">
        <b>Draft, not reviewed.</b> Ventiq Assist wrote this from what was captured in the session{{ !empty($recorded) ? ', including a machine transcript of the recording' : '' }}. No one on the team has checked it yet, so names, figures and decisions may be wrong.
    </div>
@endunless
