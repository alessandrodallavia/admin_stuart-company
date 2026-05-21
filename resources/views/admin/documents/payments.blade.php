@extends('admin.layouts.app')

@section('title', 'Pagamenti - Stuart Admin')
@section('page_title', 'Pagamenti')
@section('active_nav', 'documents')

@section('content')
    <livewire:admin.documents.payments-index :status="$currentStatus" />
@endsection
