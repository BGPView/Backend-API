<!DOCTYPE html>
<html>
    <head>
        <title>BGPView API Application</title>

        <link href="https://fonts.googleapis.com/css?family=Lato:100" rel="stylesheet" type="text/css">

        <style>
            .tsc_form_contact_light { margin:0 0 24px; width: 420px; text-align: left; }

            .tsc_form_contact_light .form-input { display: block; width: 400px; height: 24px; padding: 6px 10px; margin-bottom: 20px; font: 14px Calibri, Helvetica, Arial, sans-serif; color: #333; background: #fff; border: 1px solid #ccc; outline: none; -moz-border-radius:    8px; -webkit-border-radius: 8px; border-radius:         8px; -moz-box-shadow:    inset 0 0 1px rgba(0, 0, 0, 0.3), 0 1px 0 rgba(255, 255, 255, 0.5); -webkit-box-shadow: inset 0 0 1px rgba(0, 0, 0, 0.3), 0 1px 0 rgba(255, 255, 255, 0.5); box-shadow:         inset 0 0 1px rgba(0, 0, 0, 0.3), 0 1px 0 rgba(255, 255, 255, 0.5); -moz-background-clip:    padding; -webkit-background-clip: padding-box; background-clip:         padding-box; -moz-transition:    all 0.4s ease-in-out; -webkit-transition: all 0.4s ease-in-out; -o-transition:      all 0.4s ease-in-out; -ms-transition:     all 0.4s ease-in-out; transition:         all 0.4s ease-in-out; behavior: url(PIE.htc); }
            .tsc_form_contact_light textarea.form-input { width: 400px; height: 200px; overflow: auto; }

            .tsc_form_contact_light .form-input:focus { border: 1px solid #7fbbf9; -moz-box-shadow:    inset 0 0 1px rgba(0, 0, 0, 0.3), 0 0 3px #7fbbf9; -webkit-box-shadow: inset 0 0 1px rgba(0, 0, 0, 0.3), 0 0 3px #7fbbf9; box-shadow:         inset 0 0 1px rgba(0, 0, 0, 0.3), 0 0 3px #7fbbf9; }

            .tsc_form_contact_light .form-input:-moz-ui-invalid { border: 1px solid #e00; -moz-box-shadow:    inset 0 0 1px rgba(0, 0, 0, 0.3), 0 0 3px #e00; -webkit-box-shadow: inset 0 0 1px rgba(0, 0, 0, 0.3), 0 0 3px #e00; box-shadow:         inset 0 0 1px rgba(0, 0, 0, 0.3), 0 0 3px #e00;}
            .tsc_form_contact_light .form-input.invalid { border: 1px solid #e00; -moz-box-shadow:    inset 0 0 1px rgba(0, 0, 0, 0.3), 0 0 3px #e00; -webkit-box-shadow: inset 0 0 1px rgba(0, 0, 0, 0.3), 0 0 3px #e00; box-shadow:         inset 0 0 1px rgba(0, 0, 0, 0.3), 0 0 3px #e00; }

            .tsc_form_contact_light.nolabel ::-webkit-input-placeholder { color: #888;}
            .tsc_form_contact_light.nolabel :-moz-placeholder { color: #888;}

            .tsc_form_contact_light .form-btn { padding: 0 15px; height: 30px; font: bold 12px Calibri, Helvetica, Arial, sans-serif; text-align: center; color: #fff; text-shadow: 0 1px 0 rgba(0, 0, 0, 0.5); cursor: pointer; border: 1px solid #1972c4; outline: none; position: relative; background-color: #1d83e2; background-image: -webkit-gradient(linear, left top, left bottom, from(#77b5ee), to(#1972c4)); /* Saf4+, Chrome */ background-image: -webkit-linear-gradient(top, #77b5ee, #1972c4); /* Chrome 10+, Saf5.1+, iOS 5+ */ background-image:    -moz-linear-gradient(top, #77b5ee, #1972c4); /* FF3.6 */ background-image:     -ms-linear-gradient(top, #77b5ee, #1972c4); /* IE10 */ background-image:      -o-linear-gradient(top, #77b5ee, #1972c4); /* Opera 11.10+ */ background-image:         linear-gradient(top, #77b5ee, #1972c4); -pie-background:          linear-gradient(top, #77b5ee, #1972c4); /* IE6-IE9 */ -moz-border-radius:    16px; -webkit-border-radius: 16px; border-radius:         16px; -moz-box-shadow:    inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 1px 2px rgba(0, 0, 0, 0.5); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 1px 2px rgba(0, 0, 0, 0.5); box-shadow:         inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 1px 2px rgba(0, 0, 0, 0.5); -moz-background-clip:    padding; -webkit-background-clip: padding-box; background-clip:         padding-box; behavior: url(PIE.htc); }
            .tsc_form_contact_light .form-btn:active { border: 1px solid #77b5ee; background-color: #1972c4; background-image: -webkit-gradient(linear, left top, left bottom, from(#1972c4), to(#77b5ee)); /* Saf4+, Chrome */ background-image: -webkit-linear-gradient(top, #1972c4, #77b5ee); /* Chrome 10+, Saf5.1+, iOS 5+ */ background-image:    -moz-linear-gradient(top, #1972c4, #77b5ee); /* FF3.6 */ background-image:     -ms-linear-gradient(top, #1972c4, #77b5ee); /* IE10 */ background-image:      -o-linear-gradient(top, #1972c4, #77b5ee); /* Opera 11.10+ */ background-image:         linear-gradient(top, #1972c4, #77b5ee); -pie-background:          linear-gradient(top, #1972c4, #77b5ee); /* IE6-IE9 */ -moz-box-shadow:    inset 0 0 5px rgba(0, 0, 0, 0.5), 0 1px 0 rgba(255, 255, 255, 0.5); -webkit-box-shadow: inset 0 0 5px rgba(0, 0, 0, 0.5), 0 1px 0 rgba(255, 255, 255, 0.5); box-shadow:         inset 0 0 5px rgba(0, 0, 0, 0.5), 0 1px 0 rgba(255, 255, 255, 0.5); }
            .tsc_form_contact_light input[type=submit]::-moz-focus-inner { border: 0; padding: 0;}

            .tsc_form_contact_light label { margin-bottom: 10px; display: block; width: 300px; color: #444; font-weight: bold; text-shadow: 0 1px 1px rgba(255, 255, 255, 0.5); }
            .tsc_form_contact_light label span { font-size: 12px; font-weight: normal; color: #999; }

            .tsc_form_contact_light.frame { padding: 20px; background-color: #ccc; background-image: -webkit-gradient(linear, left top, left bottom, from(#ededed), to(#b4b4b4)); /* Saf4+, Chrome */ background-image: -webkit-linear-gradient(top, #f6f6f6, #d2d1d0); /* Chrome 10+, Saf5.1+, iOS 5+ */ background-image:    -moz-linear-gradient(top, #f6f6f6, #d2d1d0); /* FF3.6 */ background-image:     -ms-linear-gradient(top, #f6f6f6, #d2d1d0); /* IE10 */ background-image:      -o-linear-gradient(top, #f6f6f6, #d2d1d0); /* Opera 11.10+ */ background-image:         linear-gradient(top, #f6f6f6, #d2d1d0); -pie-background:          linear-gradient(top, #f6f6f6, #d2d1d0); /* IE6-IE9 */ -moz-border-radius:    8px; -webkit-border-radius: 8px; border-radius:         8px; -moz-box-shadow:    0 1px 2px rgba(0, 0, 0, 0.5), inset 0 0 1px rgba(255, 255, 255, 0.5); -webkit-box-shadow: 0 1px 2px rgba(0, 0, 0, 0.5), inset 0 0 1px rgba(255, 255, 255, 0.5); box-shadow:         0 1px 2px rgba(0, 0, 0, 0.5), inset 0 0 1px rgba(255, 255, 255, 0.5); behavior: URL(PIE.htc); }

            .tsc_form_contact_light.tbar { padding: 0 20px 20px 20px; background-color: #eee; background-image: -webkit-gradient(linear, left top, left bottom, from(#f6f6f6), to(#d6d6d6)); /* Saf4+, Chrome */ background-image: -webkit-linear-gradient(top, #f6f6f6, #d6d6d6); /* Chrome 10+, Saf5.1+, iOS 5+ */ background-image:    -moz-linear-gradient(top, #f6f6f6, #d6d6d6); /* FF3.6 */ background-image:     -ms-linear-gradient(top, #f6f6f6, #d6d6d6); /* IE10 */ background-image:      -o-linear-gradient(top, #f6f6f6, #d6d6d6); /* Opera 11.10+ */ background-image:         linear-gradient(top, #f6f6f6, #d6d6d6); -pie-background:          linear-gradient(top, #f6f6f6, #d6d6d6); /* IE6-IE9 */ behavior: URL(PIE.htc); }
            .tsc_form_contact_light.tbar h3 { font: normal 18px/1 Calibri, Helvetica, Arial, sans-serif; color: #333; text-shadow: 0 1px 1px rgba(255, 255, 255, 0.7); padding: 20px; margin: 0 -20px 20px -20px; background-color: #c9c9c9; background-image: -webkit-gradient(linear, left top, left bottom, from(#f6f6f6), to(#c9c9c9)); /* Saf4+, Chrome */ background-image: -webkit-linear-gradient(top, #f6f6f6, #c9c9c9); /* Chrome 10+, Saf5.1+, iOS 5+ */ background-image:    -moz-linear-gradient(top, #f6f6f6, #c9c9c9); /* FF3.6 */ background-image:     -ms-linear-gradient(top, #f6f6f6, #c9c9c9); /* IE10 */ background-image:      -o-linear-gradient(top, #f6f6f6, #c9c9c9); /* Opera 11.10+ */ background-image:         linear-gradient(top, #f6f6f6, #c9c9c9); -pie-background:          linear-gradient(top, #f6f6f6, #c9c9c9); /* IE6-IE9 */ -moz-border-radius:    8px 8px 0 0; -webkit-border-radius: 8px 8px 0 0; border-radius:         8px 8px 0 0; -moz-border-radius:    8px 8px 0 0; -moz-box-shadow:    inset 0 1px 0 rgba(255, 255, 255, 0.5), 0 1px 1px rgba(0, 0, 0, 0.5); -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5), 0 1px 1px rgba(0, 0, 0, 0.5); box-shadow:         inset 0 1px 0 rgba(255, 255, 255, 0.5), 0 1px 1px rgba(0, 0, 0, 0.5); behavior: url(PIE.htc); }


            html, body {
                height: 100%;
            }

            body {
                margin: 0;
                padding: 0;
                width: 100%;
                color: #B0BEC5;
                display: table;
                font-weight: 100;
                font-family: 'Lato';
            }

            .container {
                text-align: center;
                display: table-cell;
                vertical-align: middle;
            }

            .content {
                text-align: center;
                display: inline-block;
            }

            h1 {
                margin-bottom: 2em;
            }

            .error-msg {
                color:#f04541;
                font-size:.8em;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="content">
                <h1>BGPView API Application</h1>
                <form action="{{ route('register.application') }}" method="POST" class="tsc_form_contact_light nolabel">
                    {!! $errors->first('name', '<span class="error-msg">:message</span>') !!}
                    <input type="text" name="name" class="form-input" placeholder="Name (required)" value="{{ Input::old('name') }}" required />

                    {!! $errors->first('email', '<span class="error-msg">:message</span>') !!}
                    <input type="email" name="email" class="form-input" placeholder="Contact Email (required)" value="{{ Input::old('email') }}" required />

                    {!! $errors->first('usage', '<span class="error-msg">:message</span>') !!}
                    <textarea name="usage" class="form-input"  placeholder="What your usage plans for the API (required)" required>{{ Input::old('usage') }}</textarea>
                    <input class="form-btn" type="submit" value="Get API Key" />
                </form>
            </div>
        </div>
    </body>
</html>
