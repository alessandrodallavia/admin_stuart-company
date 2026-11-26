<label class="mt-12 flex cursor-pointer items-start gap-8 text-12 leading-relaxed">
    <input type="hidden" name="notify_customer" value="0">
    <input type="checkbox" name="notify_customer" value="1" data-notify-customer @checked((bool) old('notify_customer', true)) class="mt-2 cursor-pointer rounded border-gray-mid text-bullstar focus:ring-bullstar">
    <span>Invia comunicazione al cliente<small class="mt-4 block text-11 text-gray">Quando prevista per questo aggiornamento.</small></span>
</label>
