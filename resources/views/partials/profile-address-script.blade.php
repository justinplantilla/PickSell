@vite('resources/css/views/partials-profile-address-script.css')
<div data-profile-address data-province="{{ auth()->user()->province }}" data-municipality="{{ auth()->user()->municipality }}" data-barangay="{{ auth()->user()->barangay }}" hidden></div>
@vite('resources/js/components/profile-address.js')