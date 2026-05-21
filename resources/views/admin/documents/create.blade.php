@extends('admin.layouts.app')

@section('title', 'Nuovo documento - Stuart Admin')
@section('page_title', 'Nuovo documento')
@section('active_nav', 'documents')

@section('content')
    @include('admin.documents._form')
@endsection
