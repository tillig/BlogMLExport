using System;
using System.Collections.Generic;
using System.Text;
using System.Xml;
using System.IO;

namespace RewriteUrls
{
	class Program
	{
		static void Main(string[] args)
		{
			if (args.Length != 3)
			{
				string progName = typeof(Program).Assembly.GetName().Name;
				Console.WriteLine(progName);
				Console.WriteLine("Rewrites the BlogML file so links and image sources are updated.");
				Console.WriteLine("Usage:");
				Console.WriteLine("{0} MappingFile BlogMLFile OutputFile", progName);
				Console.WriteLine("Example:");
				Console.WriteLine("{0} idmap.xml blogml.xml rewritten.xml");
				return;
			}
			string mappingfile = args[0];
			if (!File.Exists(mappingfile))
			{
				Console.WriteLine("Unable to find ID mapping file {0}.", mappingfile);
				return;
			}
			string infile = args[1];
			if (!File.Exists(infile))
			{
				Console.WriteLine("Unable to find input file {0}.", infile);
				return;
			}
			string outfile = args[2];

			using (FileStream mapstream = File.OpenRead(mappingfile))
			using (FileStream instream = File.OpenRead(infile))
			using (FileStream outstream = File.Create(outfile))
			using (XmlUrlRewriteWriter writer = new XmlUrlRewriteWriter(outstream, Encoding.UTF8))
			{
				writer.Formatting = Formatting.Indented;
				writer.Indentation = 1;
				writer.IndentChar = '\t';

				XmlDocument map = new XmlDocument();
				map.Load(mapstream);
				XmlNodeList mappings = map.SelectNodes("/blogmlidmap/map");
				foreach (XmlNode mapping in mappings)
				{
					string oldid = mapping.Attributes["old"].Value;
					string newid = String.Format("/archive/{0}/{1}/{2}/{3}.aspx", mapping.Attributes["year"].Value, mapping.Attributes["month"].Value, mapping.Attributes["day"].Value, mapping.Attributes["new"].Value);
					writer.EntryIdMappings.Add(oldid, newid);
				}

				XmlDocument doc = new XmlDocument();
				doc.Load(instream);
				doc.WriteContentTo(writer);
			}

			Console.WriteLine("Done.");
		}
	}
}
