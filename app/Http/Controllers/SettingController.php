<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\Appointment;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\Specialization;
use App\Models\State;
use App\Repositories\SettingRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Laracasts\Flash\Flash;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class SettingController extends AppBaseController
{
    /**
     * @var SettingRepository
     */
    private $settingRepository;

    /**
     * SettingController constructor.
     */
    public function __construct(SettingRepository $SettingRepository)
    {
        $this->settingRepository = $SettingRepository;
    }

    /**
     * @return Application|Factory|View
     */
    public function index(Request $request): \Illuminate\View\View
    {
        $setting = Setting::pluck('value', 'key')->toArray();
        $sectionName = ($request->get('section') === null) ? 'general' : $request->get('section');
        $states = $cities = [];
        if (isset($setting['country_id'])) {
            $states = getStates($setting['country_id']);
        }
        if (isset($states)) {
            $cities = getCities($states);
        }
        $countries = Country::toBase()->pluck('name', 'id');
        $specialities = Specialization::orderBy('name', 'asc')->pluck('name', 'id');
        $currencies = Currency::toBase()->pluck('currency_name', 'id');
        $paymentGateways = Appointment::PAYMENT_METHOD;
        $languages = User::LANGUAGES;
        $courentlanguage = Setting::where('key','language')->get()->toArray()[0]['value'];
        $selectedPaymentGateways = PaymentGateway::pluck('payment_gateway')->toArray();

        return view("setting.$sectionName",
            compact('sectionName', 'setting', 'countries', 'specialities', 'states', 'cities', 'currencies','languages','courentlanguage', 'paymentGateways', 'selectedPaymentGateways'));
    }

    /**
     * AP-20: Dedicated endpoint to save ONLY the Jotform API key.
     *
     * The generic settings form runs `UpdateSettingRequest` which, for
     * the `smtp` section, requires `mail_username`, `mail_from_name` and
     * `mail_from_address`. If any of those is blank, validation fails
     * and the user is redirected back with errors — the jotform_api_key
     * never reaches the DB. This endpoint skips that validation so the
     * admin can save the Jotform key independently of SMTP setup state.
     */
    public function saveJotformKey(Request $request): \Illuminate\Http\JsonResponse
    {
        $key = trim((string) $request->input('jotform_api_key', ''));
        $row = Setting::firstOrCreate(
            ['key' => 'jotform_api_key'],
            ['value' => '']
        );
        $row->update(['value' => $key]);
        \Cache::flush('settings');
        \Cache::put('settings', Setting::all()->keyBy('key'));
        \Log::info('[saveJotformKey] saved', [
            'len'    => strlen($key),
            'masked' => $key === '' ? '(empty)' : substr($key, 0, 4) . '…' . substr($key, -4),
        ]);
        return response()->json(['success' => true, 'message' => 'Jotform API key saved']);
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        // AP-02 / CP-12 diagnostic: log every settings save so we can see
        // whether the Jotform API key field is actually arriving. Grep
        // `storage/logs/laravel.log` for "[settings.update]".
        \Log::info('[settings.update] incoming', [
            'section'         => $request->input('sectionName'),
            'has_jotform_key' => $request->has('jotform_api_key'),
            'jotform_key_len' => strlen((string) $request->input('jotform_api_key', '')),
            'input_keys'      => array_keys($request->except(['_token', 'password', 'mail_password'])),
        ]);

        // AP-12: Bulletproof save for the Jotform API key. The generic repo
        // path iterates and updates by key — but if any upstream code (XSS,
        // Livewire, a middleware) strips the field, that loop silently skips
        // it. Handling the key explicitly HERE — with its own log line —
        // guarantees that if it reaches this controller it reaches the DB.
        if ($request->has('jotform_api_key')) {
            $jotformKey = trim((string) $request->input('jotform_api_key'));
            // CP-39: Preserve existing key when the field is submitted
            // empty. The SMTP page's "Save Changes" button posts the
            // whole form — admins editing other fields shouldn't wipe
            // the API key just because they didn't re-paste it. The
            // dedicated "Save Key" AJAX endpoint remains the way to
            // explicitly clear/replace the key.
            if ($jotformKey === '') {
                \Log::info('[settings.update] jotform_api_key empty — preserved existing value');
            } else {
                $row = Setting::where('key', 'jotform_api_key')->first();
                if (! $row) {
                    // First-time install without the row — create it.
                    Setting::create(['key' => 'jotform_api_key', 'value' => $jotformKey]);
                } else {
                    $row->update(['value' => $jotformKey]);
                }
                \Log::info('[settings.update] jotform_api_key persisted', [
                    'key_len' => strlen($jotformKey),
                    'masked'  => substr($jotformKey, 0, 4) . '…' . substr($jotformKey, -4),
                ]);
            }
        }

        $language = $request->language;
        if(!empty($language)){
            Setting::where('key','language')->update([
                'value' => $language,
            ]);
            $appointment = user::whereNot('type',User::ADMIN)->get();
            foreach ($appointment as $appointment) {
                if($language == null)
                {
                    $appointment->update([
                        'language' => 'en',
                    ]);
                }else{
                    $appointment->update([
                        'language' => $language,
                    ]);
                }
                session()->forget('languageName');
            }
        }
        // $paymentGateways = $request->payment_gateway;
        // if (! empty($paymentGateways)) {
        //     PaymentGateway::query()->delete();
        // }

        // if (isset($paymentGateways)) {
        //     foreach ($paymentGateways as $paymentGateway) {
        //         PaymentGateway::updateOrCreate(['payment_gateway_id' => $paymentGateway],
        //             [
        //                 'payment_gateway' => Appointment::PAYMENT_METHOD[$paymentGateway],
        //             ]);
        //     }
        // }

        $id = Auth::id();

        if ($request->recaptcha == 1 &&  empty($request->googleCaptchaKey)) {
            Flash::error(__('messages.common.google_captcha_key_required'));
        }elseif($request->recaptcha == 1 &&  empty($request->googleCaptchaSecret)){
            Flash::error(__('messages.common.google_captcha_secret_required'));
        }else{
            $this->settingRepository->update($request->all(), $id);
            Flash::success(__('messages.flash.setting_update'));
        }

        return Redirect::back();
    }

    /**
     * @return mixed
     */
    public function getStates(Request $request)
    {
        $countryId = $request->get('settingCountryId');
        $data['state_id'] = getSettingValue('state_id');
        $data['states'] = State::where('country_id', $countryId)->toBase()->pluck('name', 'id')->toArray();

        return $this->sendResponse($data, __('messages.flash.states_retrieve'));
    }

    /**
     * @return mixed
     */
    public function getCities(Request $request)
    {
        $state_id = $request->get('stateId');
        $data['city_id'] = getSettingValue('city_id');
        $data['cities'] = City::where('state_id', $state_id)->toBase()->pluck('name', 'id')->toArray();

        return $this->sendResponse($data, __('messages.flash.cities_retrieve'));
    }
}
