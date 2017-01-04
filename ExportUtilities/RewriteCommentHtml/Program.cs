using System;
using System.Collections.Generic;
using System.Text;
using System.Xml;
using System.IO;

namespace RewriteCommentHtml
{
	class Program
	{
		static void Main(string[] args)
		{
			if (args.Length != 2)
			{
				string progName = typeof(Program).Assembly.GetName().Name;
				Console.WriteLine(progName);
				Console.WriteLine("Rewrites the BlogML file so comment HTML gets updated.");
				Console.WriteLine("Usage:");
				Console.WriteLine("{0} BlogMLFile OutputFile", progName);
				Console.WriteLine("Example:");
				Console.WriteLine("{0} blogml.xml rewritten.xml");
				return;
			}

			string infile = args[0];
			if (!File.Exists(infile))
			{
				Console.WriteLine("Unable to find input file {0}.", infile);
				return;
			}
			string outfile = args[1];

			using (FileStream instream = File.OpenRead(infile))
			using (FileStream outstream = File.Create(outfile))
			using (XmlCommentHtmlRewriteWriter writer = new XmlCommentHtmlRewriteWriter(outstream, Encoding.UTF8))
			{
				writer.Formatting = Formatting.Indented;
				writer.Indentation = 1;
				writer.IndentChar = '\t';

				XmlDocument doc = new XmlDocument();
				doc.Load(instream);
				doc.WriteContentTo(writer);
			}

			Console.WriteLine("Done.");
		}
	}
}
