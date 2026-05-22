@extends('admin.layouts.app')

@section('title', 'Importa XML fatture - Stuart Admin')
@section('page_title', 'Importa XML fatture')
@section('active_nav', 'documents')

@section('content')
    <livewire:admin.documents.import-xml />
@endsection
