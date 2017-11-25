@extends('layouts.master')

@section('title', 'Renters')

@section('content')

    <div class="row">

        <div class="col-12">

            <div class="card-heading">All current renters <a href="/renters/new">[Add new]</a></div>
            
            <table>
                <thead>
                    <tr>
                        <th>Moon</th>
                        <th>Rental contact</th>
                        <th>Notes</th>
                        <th class="numeric">Monthly fee</th>
                        <th class="numeric">Start date</th>
                        <th>Edit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($renters as $renter)
                        <tr>
                            <td>
                                @if (isset($renter->refinery_id))
                                    {{ $renter->refinery->name }} ({{ $renter->refinery->system->solarSystemName }})
                                @else
                                    N/A
                                @endif
                            </td>
                            <td>{{ $renter->character->name }}</td>
                            <td>{{ $renter->notes }}</td>
                            <td class="numeric">{{ number_format($renter->monthly_rental_fee, 0) }} ISK</td>
                            <td class="numeric">{{ date('jS F Y', strtotime($renter->start_date)) }}</td>
                            <td><a href="/renters/{{ $renter->id }}">Edit details</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>

    </div>

@endsection
