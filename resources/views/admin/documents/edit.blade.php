@extends('admin.layouts.app')

@section('title', 'Modifica documento - Stuart Admin')
@section('page_title', 'Modifica documento')
@section('active_nav', 'documents')

@section('content')
    @include('admin.documents._form')
@endsection
