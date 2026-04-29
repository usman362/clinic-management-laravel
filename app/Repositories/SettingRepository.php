<?php

namespace App\Repositories;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

/**
 * Class UserRepository
 */
class SettingRepository extends BaseRepository
{
    public $fieldSearchable = [
        'clinic_name',
    ];

    /**
     * {@inheritDoc}
     */
    public function getFieldsSearchable()
    {
        return $this->fieldSearchable;
    }

    /**
     * {@inheritDoc}
     */
    public function model()
    {
        return Setting::class;
    }

    /**
     * @throws FileIsTooBig
     * @throws FileDoesNotExist
     */
    public function update($input, $userId): void
    {
        $inputArr = Arr::except($input, ['_token']);

        if ($inputArr['sectionName'] == 'general') {
            $inputArr['clinic_name'] = (empty($inputArr['clinic_name'])) ? '' : $inputArr['clinic_name'];
            // $inputArr['contact_no'] = (empty($inputArr['contact_no'])) ? '' : $inputArr['contact_no'];
            $inputArr['email'] = (empty($inputArr['email'])) ? '' : $inputArr['email'];
            // $inputArr['specialities'] = (empty($inputArr['specialities'])) ? '1' : json_encode($inputArr['specialities']);
            $inputArr['currency'] = (empty($inputArr['currency'])) ? '1' : $inputArr['currency'];
            $inputArr['prefix'] = (empty($inputArr['prefix'])) ? '' : $inputArr['prefix'];
            $inputArr['region_code'] = (empty($inputArr['region_code'])) ? '' : $inputArr['region_code'];
            $inputArr['default_country_data'] = (empty($inputArr['default_country_data'])) ? '' : $inputArr['default_country_data'];
            $inputArr['default_country_code'] = (empty($inputArr['default_country_code'])) ? '' : $inputArr['default_country_code'];
            $inputArr['email_verified'] = (empty($inputArr['email_verified'])) ? '0' : $inputArr['email_verified'];
            // $inputArr['default_country_code'] = (empty($inputArr['default_country_code'])) ? '' : $inputArr['default_country_code'];
            $inputArr['recaptcha'] = (empty($inputArr['recaptcha'])) ? 0 : $inputArr['recaptcha'];
            $inputArr['googleCaptchaKey'] = (empty($inputArr['googleCaptchaKey'])) ? '' : $inputArr['googleCaptchaKey'];
            $inputArr['googleCaptchaSecret'] = (empty($inputArr['googleCaptchaSecret'])) ? '' : $inputArr['googleCaptchaSecret'];
            $inputArr['booking_information'] = (empty($inputArr['booking_information'])) ? '' : json_decode($inputArr['booking_information']);
        }
        if ($inputArr['sectionName'] == 'contact_information') {
            $inputArr['address_one'] = (empty($inputArr['address_one'])) ? '' : $inputArr['address_one'];
            $inputArr['address_two'] = (empty($inputArr['address_two'])) ? '' : $inputArr['address_two'];
            $inputArr['country'] = (empty($inputArr['country'])) ? '1' : $inputArr['country'];
            $inputArr['state'] = (empty($inputArr['state'])) ? '1' : $inputArr['state'];
            $inputArr['city'] = (empty($inputArr['city'])) ? '1' : $inputArr['city'];
            $inputArr['postal_code'] = (empty($inputArr['postal_code'])) ? '' : $inputArr['postal_code'];
        }

        // Keys whose existing DB value should be kept when the form submits
        // them blank (typically password/secret fields that never echo their
        // current value back to the admin). The admin can still clear them
        // explicitly later via tinker / a dedicated "clear" action.
        // CP-39: Preserve jotform_api_key too — admins editing other SMTP
        // fields shouldn't accidentally wipe the API key by hitting "Save
        // Changes" without re-pasting it.
        $preserveIfBlank = ['mail_password', 'jotform_api_key'];

        foreach ($inputArr as $key => $value) {

            /** @var Setting $setting */
            $setting = Setting::where('key', $key)->first();
            if (! $setting) {
                continue;
            }

            // Skip blank password-style fields so we don't wipe out the
            // stored value when the admin saves other settings.
            if (in_array($key, $preserveIfBlank, true)
                && (is_null($value) || $value === '')) {
                continue;
            }

            $setting->update(['value' => $value]);

            if (in_array($key, ['logo']) && ! empty($value)) {
                $setting->clearMediaCollection(Setting::LOGO);
                // dd($setting->addMedia($value));
                $media = $setting->addMedia($value)->toMediaCollection(Setting::LOGO, config('app.media_disc'));
                $setting->update(['value' => $media->getUrl()]);
            }

            if (in_array($key, ['favicon']) && ! empty($value)) {
                $setting->clearMediaCollection(Setting::FAVICON);
                $media = $setting->addMedia($value)->toMediaCollection(Setting::FAVICON, config('app.media_disc'));
                $setting->update(['value' => $media->getUrl()]);
            }
            if ($key == 'google_credentials' && !empty($value)) {
                
                $directory = resource_path('google-oath');

                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                // original file name preserve karega
                $fileName = $value->getClientOriginalName();

                $value->move($directory, $fileName);

                $setting->update([
                    'value' => $fileName
                ]);
            }
        }

        Cache::flush('settings');
        Cache::put('settings', Setting::all()->keyBy('key'));
    }
}
