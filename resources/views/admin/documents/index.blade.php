@extends('admin.layouts.app')

@section('title', 'Documenti - Stuart Admin')
@section('page_title', 'Documenti')
@section('active_nav', 'documents')

@section('content')
    <livewire:admin.documents.index />
@endsection
