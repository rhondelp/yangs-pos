<x-layouts.app :title="__('New product')">
    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
        @include('products._form')
    </form>
</x-layouts.app>
