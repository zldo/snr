unit daemonmapp;

{$mode objfpc}{$H+}

interface

uses
  Classes, SysUtils, FileUtil, DaemonApp;

type

  { TSnrDaemonMapper }

  TSnrDaemonMapper = class(TDaemonMapper)
    procedure SnrDaemonMapperRun(Sender: TObject);
  private
    { private declarations }
  public
    { public declarations }
  end;

var
  SnrDaemonMapper: TSnrDaemonMapper;

implementation

procedure RegisterMapper;
begin
  RegisterDaemonMapper(TSnrDaemonMapper)
end;

{$R *.lfm}

{ TSnrDaemonMapper }

procedure TSnrDaemonMapper.SnrDaemonMapperRun(Sender: TObject);
begin

end;


initialization
  RegisterMapper;
end.

