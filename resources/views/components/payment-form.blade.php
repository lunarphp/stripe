<div x-data="{
  stripe: null,
  handleSubmit() {
    console.log('hello')
    this.stripe.confirmPayment({
      elements,
      confirmParams: {
        // Make sure to change this to your payment completion page
        return_url: '{{ $returnUrl }}',
      },
    }).finally(response => {

    });
  },
  init() {
    this.stripe = Stripe('{{ config('services.stripe.public_key')}}');

    elements = this.stripe.elements({
      clientSecret: '{{ $this->clientSecret }}'
    });

    const paymentElement = elements.create('payment');
    paymentElement.mount(this.$refs.paymentElement);

  }
}">
  <!-- Display a payment form -->
  <form x-ref="payment-form" x-on:submit.prevent="handleSubmit()">
    <div x-ref="paymentElement">
      <!--Stripe.js injects the Payment Element-->
    </div>
    {{-- <button id="submit">
      <div class="hidden spinner" id="spinner"></div>
      <span id="button-text">Pay now</span>
    </button> --}}
    <div class="mt-4">
      <button
        class="px-5 py-3 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-500"
        type="submit"
      >
        <span
          wire:loading.remove.delay
          wire:target="checkout"
        >
          Make Payment
        </span>
          <span
            wire:loading.delay
            wire:target="checkout"
          >
            <svg
              class="w-5 h-5 text-white animate-spin"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
              ></circle>
              <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              ></path>
            </svg>
          </span>
      </button>
    </div>
    <div id="payment-message" class="hidden"></div>
  </form>
</div>