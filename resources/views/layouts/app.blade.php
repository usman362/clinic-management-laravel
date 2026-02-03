<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>@yield('title') | {{ getAppName() }}</title>
    <!-- Favicon -->
    <link rel="icon" href="{{ asset(getAppFavicon()) }}" type="image/png">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- General CSS Files -->

    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/third-party.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ mix('assets/css/pages.css') }}">

    <style>
        .listing-skeleton {
            .card {
                height: 750px;
            }

            .pulsate {
                background: linear-gradient(-45deg, #dddddd, #f0f0f0, #dddddd, #f0f0f0);
                background-size: auto;
                -webkit-animation: Gradient 2.25s ease infinite;
                -moz-animation: Gradient 2.25s ease infinite;
                animation: Gradient 2.25s ease infinite;
                border-radius: 10px;
            }


            .card-content {
                clear: both;
                box-sizing: border-box;
                padding: 16px;
                background: #fff;
            }


            .search-box {
                width: 300px;
                height: 40px;
                margin-top: 8px;
                margin-left: 5px;
            }


            .date-box {
                width: 200px;
                height: 40px;
                margin-top: 8px;
                margin-left: 5px;
            }

            .listing {
                width: 400px;
                height: 42px;
                margin-top: 8px;
                margin-left: 5px;
            }


            .filter-box {
                width: 50px;
                height: 40px;
                margin-top: 8px;
                margin-left: auto;

            }


            .export-box {
                width: 100px;
                height: 40px;
                margin-top: 8px;
                margin-left: 5px;
            }


            .add-button-box {
                width: 110px;
                height: 40px;
                margin-top: 8px;
                margin-left: 5px;
            }

            .add-button {
                width: 137px;
                height: 41px;
                margin-top: 8px;
                margin-left: 5px;
            }

            .add-button-box-lg {
                width: 200px;
                height: 40px;
                margin-top: 8px;
                margin-left: 5px;
            }

            .table {
                width: 100%;
                height: 45px;
                margin-top: 8px;
                margin-left: 5px;
            }


            .column-box {
                height: 45px;
                margin-top: 8px;
                margin-left: 10px;
            }


            @-webkit-keyframes Gradient {
                0% {
                    background-position: 0% 50%;
                }


                50% {
                    background-position: 100% 50%;
                }


                100% {
                    background-position: 0% 50%;
                }
            }


            @-moz-keyframes Gradient {
                0% {
                    background-position: 0% 50%;
                }


                50% {
                    background-position: 100% 50%;
                }


                100% {
                    background-position: 0% 50%;
                }
            }


            @keyframes Gradient {
                0% {
                    background-position: 0% 50%;
                }


                50% {
                    background-position: 100% 50%;
                }


                100% {
                    background-position: 0% 50%;
                }
            }
        }
    </style>

    @if (!Auth::user()->dark_mode)
        <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('css/plugins.css') }}">
    @else
        <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/style-dark.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ asset('css/plugins.dark.css') }}">
        <link rel="stylesheet" type="text/css" href="{{ mix('assets/css/custom-pages-dark.css') }}">
    @endif

    <!-- Fonts -->
    <link rel="stylesheet" href="//fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link rel="stylesheet" href="{{ asset('assets/css/booking.css') }}">
    {{-- @livewireStyles --}}
    @routes
    @livewireStyles
    @livewireScripts
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="{{ mix('js/third-party.js') }}"></script>
    <script src="{{ mix('js/pages.js') }}"></script>
    @role('patient')
        <script src="{{ asset('assets/js/booking.js') }}"></script>
    @else
        <script src="{{ asset('assets/js/admin-booking.js') }}"></script>
    @endrole
    @php
        $bloodGroupArr = json_encode(App\Models\Doctor::BLOOD_GROUP_ARRAY);
        $bloodGroupArr = html_entity_decode($bloodGroupArr);
    @endphp
    <script data-turbo-eval="false">
        let stripe = '';
        @if (config('services.stripe.key'))
            stripe = Stripe('{{ config('services.stripe.key') }}');
        @endif
        let usersRole = '{{ !empty(getLogInUser()->roles->first()) ? getLogInUser()->roles->first()->name : '' }}';
        let currencyIcon = '{{ getCurrencyIcon() }}';
        let isSetFirstFocus = true;
        let womanAvatar = '{{ url(asset('web/media/avatars/female.jpg')) }}';
        let manAvatar = '{{ url(asset('web/media/avatars/male.jpg')) }}';
        let changePasswordUrl = "{{ route('user.changePassword') }}";
        let updateLanguageURL = "{{ route('change-language') }}";
        let phoneNo = '';
        let dashboardChartBGColor = "{{ Auth::user()->dark_mode ? '#13151f' : '#FFFFFF' }}";
        let dashboardChartFontColor = "{{ Auth::user()->dark_mode ? '#FFFFFF' : '#000000' }}";
        let userRole = '{{ getLogInUser()->hasRole('patient') }}';
        let appointmentStripePaymentUrl = '{{ url('appointment-stripe-charge') }}';
        let checkLanguageSession = '{{ checkLanguageSession() }}'
        let noData = "{{ __('messages.common.no_data_available') }}"
        let defaultCountryCodeValue = "{{ getSettingValue('default_country_code') }}";
        let currentLoginUserId = "{{ getLogInUserId() }}";
        let prescriptionStatusRoute =
            "{{ isRole('doctor') ? 'doctors.prescription.status' : (isRole('patient') ? 'patients.prescription.status' : 'prescription.status') }}";
        let startcardStatusRoute =
            "{{ isRole('doctor') ? 'doctors.card.status' : (isRole('clinic_admin') ? 'card.status' : 'card.status') }}";
        let samartCardDelete =
            "{{ isRole('doctor') ? 'doctors.smart-patient-cards.destroy' : (isRole('clinic_admin') ? 'smart-patient-cards.destroy' : 'smart-patient-cards.destroy') }}";
        let GeneratePatientCardDelete =
            "{{ isRole('doctor') ? 'doctors.generate-patient-smart-cards.destroy' : (isRole('clinic_admin') ? 'generate-patient-smart-cards.destroy' : 'generate-patient-smart-cards.destroy') }}";
        let showPatientSmartCard =
            "{{ isRole('doctor') ? 'doctors.card.detail' : (isRole('patient') ? 'patients.card.detail' : (isRole('clinic_admin') ? 'card.detail' : 'card.detail')) }}";
        let smartCardQrCode =
            "{{ isRole('doctor') ? 'doctors.card.qr' : (isRole('patient') ? 'patients.card.qr' : (isRole('clinic_admin') ? 'card.qr' : 'card.qr')) }}";
        let bloodGroupArray = @json($bloodGroupArr);
        Lang.setLocale(checkLanguageSession);
        let options = {
            'key': "{{ config('payments.razorpay.key') }}",
            'amount': 0, //  100 refers to 1
            'currency': 'INR',
            'name': "{{ getAppName() }}",
            'order_id': '',
            'description': '',
            'image': '{{ asset(getAppLogo()) }}', // logo here
            'callback_url': "{{ route('razorpay.success') }}",
            'prefill': {
                'email': '', // recipient email here
                'name': '', // recipient name here
                'contact': '', // recipient phone here
                'appointmentID': '', // appointmentID here
            },
            'readonly': {
                'name': 'true',
                'email': 'true',
                'contact': 'true',
            },
            'theme': {
                'color': '#4FB281',
            },
            'modal': {
                'ondismiss': function() {
                    displayErrorMessage(Lang.get('js.appointment_created_payment_not_complete'));
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                },
            },
        }
    </script>
</head>
@php $styleCss = 'style'; @endphp

<body>
    <div class="d-flex flex-column flex-root">
        <div class="d-flex flex-row flex-column-fluid">
            @include('layouts.sidebar')
            <div class="wrapper d-flex flex-column flex-row-fluid">
                <div class='container-fluid d-flex align-items-stretch justify-content-between px-0'>
                    @include('layouts.header')
                </div>
                <div class='content d-flex flex-column flex-column-fluid pt-7'>
                    @yield('header_toolbar')
                    {{-- <div class='d-flex flex-column-fluid'> --}}
                    <div class="">
                        @yield('content')
                    </div>
                </div>
                <div class='container-fluid'>
                    @include('layouts.footer')
                </div>
            </div>
        </div>
        {{ Form::hidden('currentLanguage', getLoginUser()->language != null ? getLoginUser()->language : checkLanguageSession(), ['class' => 'currentLanguage']) }}
    </div>

    @include('profile.changePassword')
    @include('profile.email_notification')
    @include('profile.changelanguage')

    <script>

        $('#addAppointment').on('click', function(e) {
            let appointmentIndex = $('.appointments-wrapper .appointments-section').length;
            e.preventDefault();

            let $source = $('.appointments-section:first');

            // 1️⃣ Destroy select2 ONLY on source selects
            $source.find('select[data-control="select2"]').each(function() {
                if ($(this).hasClass("select2-hidden-accessible")) {
                    $(this).select2('destroy');
                }
            });

            // 2️⃣ Clone section
            let $clone = $source.clone(false);

            $clone.attr('data-index', appointmentIndex);

            // 3️⃣ Reset values + fix names
            $clone.find('input, select').each(function() {
                let name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/\[\d+\]/, `[${appointmentIndex}]`));
                }
                $(this).val('');
            });

            // 4️⃣ Remove leftover select2 DOM from clone
            $clone.find('.select2').remove();

            $clone.find('.adminAppointmentDoctorId').empty();
            $clone.find('.adminAppointmentDoctorId').append(
                $('<option value=""></option>').text('Select Doctor')
            );

            $clone.find(".appointmentDate").flatpickr({
                locale: e,
                minDate: new Date,
                disableMobile: !0
            })

            $clone.find('.appointment-slot-data').empty();
            $clone.find('.timeSlot, .toTime').val('');

            $clone.find('.remove-appointment').removeClass('d-none');
            $clone.find('.duration-details').addClass('d-none');
            $clone.find('.duration').text('Select a service to view duration');
            // 5️⃣ Append clone
            $('.appointments-wrapper').append($clone);

            // 6️⃣ Re-initialize Select2 on ALL sections (original + clones)
            $('.appointments-wrapper')
                .find('select[data-control="select2"]')
                .select2({
                    width: '100%'
                });

            appointmentIndex++;
        });

        $(document).on('click', '.remove-appointment', function() {
            $(this).closest('.appointments-section').remove();
            calculateTotal();
        });

        function calculateTotal() {
            let total = 0;

            $('.appointment-charge').each(function() {
                total += parseFloat($(this).val() || 0);
            });

            $('#payableAmount').val(total.toFixed(2));
        }

        $(document).on('change', '.appointment-charge', calculateTotal);

        $(document).ready(function() {

            $(document).on('input', '#first_name', function() {
                let last_name = $('#last_name').val();
                $('.client_name').val($(this).val() + ' ' + last_name);
            })

            $(document).on('input', '#last_name', function() {
                let first_name = $('#first_name').val();
                $('.client_name').val(first_name + ' ' + $(this).val());
            })

            $(document).on('input', '#dob', function() {
                $('.client_dob').val($(this).val());
            })

            $(document).on('input', '#tax_code', function() {
                $('.client_tax_code').val($(this).val());
            })

            $(document).on('input', '#school_name', function() {
                $('.client_school_name').val($(this).val());
            })

            $(document).on('input', '#school_grade', function() {
                $('.client_school_grade').val($(this).val());
            })
        })

        $(document).ready(function() {
            $('form').attr('autocomplete', 'off');
            $('input, textarea, select').attr({
                autocomplete: 'off',
                autocorrect: 'off',
                autocapitalize: 'off',
                spellcheck: 'false'
            });
        });
    </script>
</body>

</html>
