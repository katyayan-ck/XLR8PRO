@extends(backpack_view('blank'))

@section('content')

<style>
    .preview-page {
        width: 100%;
        max-width: 1400px;
        margin: auto;
        padding: 10px;
    }

    .box {
        border: 1px solid #dcdcdc;
        padding: 10px;
        margin-bottom: 10px;
    }

    .box-title {
        font-size: 12px;
        font-weight: bold;
        letter-spacing: 2px;
        margin-bottom: 10px;
    }

    .compact-table {
        width: 100%;
    }

    .compact-table td {
        padding: 2px 4px;
        font-size: 13px;
    }

    .label {
        width: 35%;
        color: #666;
    }

    @media print {

        .no-print {
            display: none;
        }

        @page {
            size: A4;
            margin: 5mm;
        }
    }
</style>

<div class="preview-page">

    <div class="no-print text-end mb-3">

        <button onclick="window.print()" class="btn btn-primary">

            Print

        </button>

    </div>

    <img src="{{ asset('images/otf-header.png') }}" style="width:100%;margin-bottom:10px;">

    <div class="row">

        <div class="col-md-8">

            <div class="box">

                <h4>
                    Vehicle Order Summary
                </h4>

                Customer :
                <b>{{ $booking->name }}</b>

            </div>

        </div>

        <div class="col-md-4">

            <div class="box">

                <b>VOTF :</b> {{ $booking->order }}<br>

                <b>DMS :</b> {{ $booking->dms_no }}<br>

                <b>Regn :</b> {{ $booking->registration_no }}

            </div>

        </div>

    </div>

    <div class="row">

        <div class="col-md-4">

            <div class="box">

                <div class="box-title">
                    CUSTOMER
                </div>

                <table class="compact-table">

                    <tr>
                        <td class="label">Name</td>
                        <td>{{ $booking->name }}</td>
                    </tr>

                    <tr>
                        <td class="label">Mobile</td>
                        <td>{{ $booking->mobile }}</td>
                    </tr>

                    <tr>
                        <td class="label">PAN</td>
                        <td>{{ $booking->pan_no }}</td>
                    </tr>

                    <tr>
                        <td class="label">Aadhaar</td>
                        <td>{{ $booking->adhar_no }}</td>
                    </tr>

                </table>

            </div>

        </div>

        <div class="col-md-4">

            <div class="box">

                <div class="box-title">
                    ADDRESS
                </div>

                Address here

            </div>

        </div>

        <div class="col-md-4">

            <div class="box">

                <div class="box-title">
                    SELECTED
                </div>

                Exchange<br>
                Insurance<br>
                RSA<br>

            </div>

        </div>

    </div>

</div>

@endsection